<?php

namespace App\Http\Controllers;

use App\Models\EscrowTransaction;
use App\Models\Order;
use App\Models\Payment;
use App\Models\Product;
use App\Models\TrustScore;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use App\Traits\ApiResponse;

class PaymentController extends Controller
{
    use ApiResponse;
    // ──────────────────────────────────────────────────────────────────────────
    // INITIATE PAYMENT
    // ──────────────────────────────────────────────────────────────────────────

    /**
     * POST /api/orders/{order}/pay
     *
     * Validates the payment amount against the order type, then holds funds
     * in escrow. Stock is permanently deducted only on confirmReceipt().
     *
     * Payment gateway: STUBBED (simulated success).
     * To integrate a real gateway (MTN MoMo, Bizao, etc.), replace the
     * $gatewayResult block below with the actual API call.
     */
    public function initiate(Request $request, Order $order)
    {
        $user = $request->user();

        if ($order->buyer_id !== $user->id) {
            return $this->error('Only the buyer can pay for this order.', 403);
        }

        if ($order->status !== Order::STATUS_PENDING) {
            return $this->error('Order is not in a payable state.', 422);
        }

        // Validate amount
        $expectedAmount = $this->expectedPaymentAmount($order);

        $data = $request->validate([
            'amount' => "required|numeric|min:{$expectedAmount}|max:{$expectedAmount}",
        ]);

        // Prevent duplicate payment
        $existingPayment = Payment::where('order_id', $order->id)
            ->where('status', 'success')
            ->exists();

        if ($existingPayment) {
            return $this->error('Order is already paid.', 422);
        }

        DB::transaction(function () use ($order, $data) {
            // ── STUB: Simulate gateway call ────────────────────────────────
            // In production, replace this with:
            //   $gatewayResult = MoMoGateway::charge($data['amount'], $phoneNumber);
            //   $gatewayStatus = $gatewayResult['status']; // 'success' | 'failed'
            $gatewayStatus   = 'success';
            $gatewayResponse = ['provider' => 'stub', 'simulated' => true];
            // ─────────────────────────────────────────────────────────────

            // Record payment
            $payment = Payment::create([
                'order_id'         => $order->id,
                'amount'           => $data['amount'],
                'status'           => $gatewayStatus,
                'reference'        => 'PAY-' . strtoupper(Str::random(10)),
                'gateway_response' => $gatewayResponse,
            ]);

            if ($gatewayStatus === 'success') {
                // Hold in escrow
                EscrowTransaction::create([
                    'order_id'    => $order->id,
                    'held_amount' => $data['amount'],
                    'released'    => false,
                ]);

                // Mark order as paid
                $order->update(['status' => Order::STATUS_PAID]);
            } else {
                $payment->update(['status' => 'failed']);
            }
        });

        $order->refresh();

        if ($order->status !== Order::STATUS_PAID) {
            return $this->error('Payment failed. Please try again.', 502);
        }

        return $this->success($order->load(['payment', 'escrow']), 'Payment successful. Funds held in escrow.');
    }

    // ──────────────────────────────────────────────────────────────────────────
    // REFUND (manual trigger, e.g. admin or special cases)
    // ──────────────────────────────────────────────────────────────────────────

    /**
     * POST /api/orders/{order}/refund
     *
     * Seller sold item elsewhere → 100% refund + 15 trust deducted.
     * Triggered by seller or through admin action.
     */
    public function refund(Request $request, Order $order)
    {
        $user = $request->user();

        // Allow seller or buyer to request refund (dispute scenario)
        if (!$order->isOwnedBy($user->id)) {
            return $this->error('Unauthorized.', 403);
        }

        if ($order->status !== Order::STATUS_PAID) {
            return $this->error('Only paid orders can be refunded.', 422);
        }

        $payment = $order->payment;

        if (!$payment || !$payment->isSuccessful()) {
            return $this->error('No successful payment found for this order.', 422);
        }

        // Idempotency guard
        if ($payment->isRefunded()) {
            return $this->error('This order has already been refunded.', 422);
        }

        $data = $request->validate([
            'reason' => 'nullable|string|max:500',
        ]);

        DB::transaction(function () use ($order, $payment, $user) {
            // Mark payment refunded
            $payment->update([
                'refunded'    => true,
                'refunded_at' => now(),
            ]);

            // Audit log as negative payment
            Payment::create([
                'order_id'  => $order->id,
                'amount'    => -abs($payment->amount),
                'status'    => 'success',
                'reference' => 'REFUND-MANUAL-' . $order->id . '-' . now()->timestamp,
            ]);

            // Release escrow
            $order->escrow?->release();

            // Release reserved stock
            $product = Product::lockForUpdate()->find($order->product_id);
            if ($product) {
                $decrement = min($order->quantity, $product->stock_reserved);
                if ($decrement > 0) {
                    $product->decrement('stock_reserved', $decrement);
                }
            }

            // Trust penalty: if seller posts refund (sold elsewhere scenario)
            if ($user->id === $order->seller_id) {
                TrustScore::deduct($order->seller_id, 15);
            }

            $order->update(['status' => Order::STATUS_REFUNDED]);
        });

        return $this->success($order->fresh()->load(['payment', 'escrow']), 'Refund processed successfully.');
    }

    // ──────────────────────────────────────────────────────────────────────────
    // ESCROW STATUS
    // ──────────────────────────────────────────────────────────────────────────

    /**
     * GET /api/orders/{order}/escrow
     */
    public function escrowStatus(Request $request, Order $order)
    {
        if (!$order->isOwnedBy($request->user()->id)) {
            return $this->error('Unauthorized.', 403);
        }

        $escrow = $order->escrow;

        if (!$escrow) {
            return $this->error('No escrow record found for this order.', 404);
        }

        return $this->success([
            'escrow'         => $escrow,
            'order_status'   => $order->status,
            'payment_amount' => $order->payment?->amount,
        ]);
    }

    // ──────────────────────────────────────────────────────────────────────────
    // HELPERS
    // ──────────────────────────────────────────────────────────────────────────

    /**
     * Calculate the required payment amount based on payment type.
     */
    private function expectedPaymentAmount(Order $order): float
    {
        if ($order->payment_type === 'preorder') {
            return round($order->total_price * 0.40, 2); // 40%
        }

        return $order->total_price; // 100%
    }
}
