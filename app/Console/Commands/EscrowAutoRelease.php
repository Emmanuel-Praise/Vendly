<?php

namespace App\Console\Commands;

use App\Models\EscrowTransaction;
use App\Models\Order;
use App\Models\TrustScore;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;

class EscrowAutoRelease extends Command
{
    /**
     * The name and signature of the console command.
     */
    protected $signature = 'escrow:auto-release';

    /**
     * The console command description.
     */
    protected $description = 'Auto-release escrow funds 72 hours after payment if no dispute.';

    /**
     * Execute the console command.
     */
    public function handle(): int
    {
        $cutoff = now()->subHours(72);

        // Find all unreleased escrows where the order is in 'paid' status
        // and was created at least 72 hours ago.
        $escrows = EscrowTransaction::where('released', false)
            ->where('created_at', '<=', $cutoff)
            ->whereHas('order', function ($query) {
                $query->where('status', Order::STATUS_PAID);
            })
            ->with('order')
            ->get();

        if ($escrows->isEmpty()) {
            $this->info('No escrow transactions eligible for auto-release.');
            return self::SUCCESS;
        }

        $released = 0;

        foreach ($escrows as $escrow) {
            DB::transaction(function () use ($escrow, &$released) {
                $order = $escrow->order;

                // Double-check status inside the transaction
                $order = Order::lockForUpdate()->find($order->id);
                if (!$order || $order->status !== Order::STATUS_PAID) {
                    return;
                }

                // Release escrow
                $wasReleased = $escrow->release();

                if ($wasReleased) {
                    // Mark order as completed
                    $order->update(['status' => Order::STATUS_COMPLETED]);

                    // Reward seller +2 trust
                    TrustScore::add($order->seller_id, 2);

                    $released++;
                    $this->line("Released escrow for Order #{$order->id} (seller #{$order->seller_id}).");
                }
            });
        }

        $this->info("Auto-release complete. {$released} escrow(s) released.");
        return self::SUCCESS;
    }
}
