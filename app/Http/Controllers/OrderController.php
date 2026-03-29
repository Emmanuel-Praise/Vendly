<?php

namespace App\Http\Controllers;

use App\Models\Conversation;
use App\Models\EscrowTransaction;
use App\Models\Order;
use App\Models\Payment;
use App\Models\Product;
use App\Models\TrustScore;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use App\Traits\ApiResponse;

class OrderController extends Controller
{
    use ApiResponse;
    // ──────────────────────────────────────────────────────────────────────────
    // CREATE ORDER
    // ──────────────────────────────────────────────────────────────────────────

    /**
     * POST /api/orders
     * Create a new order with stock reservation (DB-transaction protected).
     */
    public function store(Request $request)
    {
        $data = $request->validate([
            'product_id'   => 'required|exists:products,id',
            'quantity'     => 'required|integer|min:1',
            'payment_type' => 'required|in:preorder,full',
        ]);

        $buyer = $request->user();

        try {
            $order = DB::transaction(function () use ($data, $buyer) {
                // Lock the product row to prevent race conditions
                $product = Product::lockForUpdate()->findOrFail($data['product_id']);

                // Seller cannot buy their own product
                $sellerId = $product->post->user_id;
                if ($sellerId === $buyer->id) {
                    abort(422, 'You cannot buy your own product.');
                }

                if (!$product->availability) {
                    abort(422, 'This product is no longer available.');
                }

                $available = $product->availableStock();

                if ($available < $data['quantity']) {
                    abort(422, "Only {$available} unit(s) available for this product.");
                }

                // For single-unit products, ensure no other active order exists
                if ($product->quantity === 1) {
                    $activeExists = Order::where('product_id', $product->id)
                        ->whereIn('status', [Order::STATUS_PENDING, Order::STATUS_PAID])
                        ->exists();

                    if ($activeExists) {
                        abort(422, 'This product already has an active order. Try again later.');
                    }
                }

                $unitPrice  = (float) $product->price;
                $totalPrice = $unitPrice * $data['quantity'];

                // Pre-order: 40% of total price required on payment
                // Full: 100% — stored for validation in PaymentController
                $order = Order::create([
                    'buyer_id'     => $buyer->id,
                    'seller_id'    => $sellerId,
                    'product_id'   => $product->id,
                    'quantity'     => $data['quantity'],
                    'total_price'  => $totalPrice,
                    'payment_type' => $data['payment_type'],
                    'status'       => Order::STATUS_PENDING,
                ]);

                // Reserve stock
                $product->increment('stock_reserved', $data['quantity']);

                return $order;
            });

            // Ensure a conversation exists between buyer and seller (chat trigger)
            Conversation::firstOrCreate([
                'user_one' => min($order->buyer_id, $order->seller_id),
                'user_two' => max($order->buyer_id, $order->seller_id),
            ]);

            return $this->success($order->load(['product', 'buyer', 'seller']), 'Order created successfully', 201);

        } catch (\Illuminate\Database\Eloquent\ModelNotFoundException $e) {
            return $this->error('Product not found.', 404);
        }
    }

    // ──────────────────────────────────────────────────────────────────────────
    // LIST ORDERS
    // ──────────────────────────────────────────────────────────────────────────

    /**
     * GET /api/orders
     * Return all orders for the authenticated user (as buyer or seller).
     */
    public function index(Request $request)
    {
        $user = $request->user();
        $role = $request->get('role', 'buyer'); // Default role is buyer

        $query = Order::with(['product.post', 'seller', 'buyer', 'payment']);

        if ($role === 'seller') {
            $query->where('seller_id', $user->id);
        } else {
            $query->where('buyer_id', $user->id);
        }

        $orders = $query->latest()->paginate(20);

        return $this->success($orders);
    }

    // ──────────────────────────────────────────────────────────────────────────
    // SHOW ORDER
    // ──────────────────────────────────────────────────────────────────────────

    /**
     * GET /api/orders/{order}
     */
    public function show(Request $request, Order $order)
    {
        if (!$order->isOwnedBy($request->user()->id)) {
            return $this->error('Unauthorized.', 403);
        }

        return $this->success($order->load(['product.post', 'payment', 'escrow', 'rating', 'buyer', 'seller']));
    }

    // ──────────────────────────────────────────────────────────────────────────
    // SELLER ACCEPTS ORDER
    // ──────────────────────────────────────────────────────────────────────────

    /**
     * POST /api/orders/{order}/accept
     */
    public function acceptOrder(Request $request, Order $order)
    {
        $user = $request->user();

        if ($order->seller_id !== $user->id) {
            return $this->error('Only the seller can accept this order.', 403);
        }

        if ($order->status !== Order::STATUS_PENDING) {
            return $this->error('Order cannot be accepted in its current state.', 422);
        }

        $order->update(['seller_accepted_at' => now()]);

        return $this->success($order, 'Order accepted.');
    }

    // ──────────────────────────────────────────────────────────────────────────
    // BUYER CONFIRMS RECEIPT
    // ──────────────────────────────────────────────────────────────────────────

    /**
     * POST /api/orders/{order}/confirm
     * Buyer marks item as received → releases escrow + trust points.
     */
    public function confirmReceipt(Request $request, Order $order)
    {
        $user = $request->user();

        if ($order->buyer_id !== $user->id) {
            return $this->error('Only the buyer can confirm receipt.', 403);
        }

        if ($order->status !== Order::STATUS_PAID) {
            return $this->error('Order must be in "paid" status to confirm receipt.', 422);
        }

        DB::transaction(function () use ($order) {
            // Permanently deduct stock
            $product = Product::lockForUpdate()->find($order->product_id);
            if ($product) {
                $product->decrement('quantity', $order->quantity);
                $product->decrement('stock_reserved', $order->quantity);

                // Mark unavailable if stock hits 0
                if ($product->quantity <= 0) {
                    $product->update(['availability' => false]);
                }
            }

            // Release escrow
            $order->escrow?->release();

            // Mark order completed
            $order->update(['status' => Order::STATUS_COMPLETED]);

            // Reward seller
            TrustScore::add($order->seller_id, 2);
        });

        return $this->success($order->fresh(), 'Receipt confirmed. Order completed.');
    }

    // ──────────────────────────────────────────────────────────────────────────
    // CANCEL ORDER
    // ──────────────────────────────────────────────────────────────────────────

    /**
     * POST /api/orders/{order}/cancel
     */
    public function cancel(Request $request, Order $order)
    {
        $user = $request->user();

        if (!$order->isOwnedBy($user->id)) {
            return $this->error('Unauthorized.', 403);
        }

        if (!in_array($order->status, [Order::STATUS_PENDING, Order::STATUS_PAID])) {
            return $this->error('Order cannot be cancelled in its current state.', 422);
        }

        $isBuyer  = $order->buyer_id === $user->id;
        $isSeller = $order->seller_id === $user->id;

        // ── Abuse protection: max 3 buyer cancellations per week ──────────────
        if ($isBuyer) {
            $cancelCount = Order::where('buyer_id', $user->id)
                ->where('status', Order::STATUS_CANCELLED)
                ->where('updated_at', '>=', now()->subDays(7))
                ->count();

            if ($cancelCount >= 3) {
                return $this->error('You have exceeded the 3 cancellations allowed per week.', 422);
            }
        }

        DB::transaction(function () use ($order, $isBuyer, $isSeller, $user) {
            $payment = $order->payment;

            // Release reserved stock
            $product = Product::lockForUpdate()->find($order->product_id);
            if ($product) {
                $decrement = min($order->quantity, $product->stock_reserved);
                if ($decrement > 0) {
                    $product->decrement('stock_reserved', $decrement);
                }
            }

            // ── Seller cancels ────────────────────────────────────────────────
            if ($isSeller) {
                $this->processRefund($order, $payment, 100);
                TrustScore::deduct($order->seller_id, 10);
                $order->update(['status' => Order::STATUS_CANCELLED]);
                return;
            }

            // ── Buyer cancels – before seller accepts ─────────────────────────
            if (!$order->sellerHasAccepted()) {
                $this->processRefund($order, $payment, 100);
                $order->update(['status' => Order::STATUS_CANCELLED]);
                return;
            }

            // ── Buyer cancels – after seller accepts ──────────────────────────
            if ($order->payment_type === 'preorder') {
                // 5% penalty → stays with seller
                $this->processRefund($order, $payment, 95);
            } else {
                // 5–10% penalty → stay with seller (using 10%)
                $this->processRefund($order, $payment, 90);
            }

            // Mild trust deduction for buyer
            TrustScore::deduct($order->buyer_id, 3);
            $order->update(['status' => Order::STATUS_CANCELLED]);
        });

        return $this->success($order->fresh(), 'Order cancelled.');
    }

    // ──────────────────────────────────────────────────────────────────────────
    // INTERNAL: PROCESS REFUND
    // ──────────────────────────────────────────────────────────────────────────

    /**
     * Internal helper — marks payment as refunded and releases escrow.
     *
     * @param Order    $order
     * @param Payment|null $payment
     * @param int      $percent  Percentage of amount to refund (0–100)
     */
    private function processRefund(Order $order, ?Payment $payment, int $percent): void
    {
        if ($payment && !$payment->isRefunded() && $payment->isSuccessful()) {
            $refundAmount = round($payment->amount * ($percent / 100), 2);

            // Idempotency guard – mark refunded regardless of gateway
            $payment->update([
                'refunded'    => true,
                'refunded_at' => now(),
            ]);

            // Log the refund as a new payment record for audit trail
            Payment::create([
                'order_id'  => $order->id,
                'amount'    => -abs($refundAmount), // negative = refund
                'status'    => 'success',
                'reference' => 'REFUND-' . $order->id . '-' . now()->timestamp,
            ]);
        }

        // Release escrow regardless
        $order->escrow?->release();

        // Update order to refunded if full refund
        if ($percent === 100) {
            $order->update(['status' => Order::STATUS_REFUNDED]);
        }
    }
}
