<?php

namespace App\Http\Controllers;

use App\Models\Product;
use Illuminate\Http\Request;
use App\Traits\ApiResponse;
use Carbon\Carbon;
use Illuminate\Support\Facades\DB;

class BoostController extends Controller
{
    use ApiResponse;

    /**
     * Repost a Product (Free, once per hour)
     * Moves product to the top of listings.
     */
    public function repost(Request $request, Product $product)
    {
        $user = $request->user();

        // Authorization
        if ($product->post->user_id !== $user->id) {
            return $this->error('Unauthorized.', 403);
        }

        // Frequency Limit (60 minutes)
        if ($product->updated_at && $product->updated_at->diffInMinutes(now()) < 60) {
            $remaining = 60 - $product->updated_at->diffInMinutes(now());
            return $this->error("Please wait {$remaining} minutes before reposting again.", 422);
        }

        $product->touch(); // Updates updated_at to now()

        return $this->success(null, 'Product successfully reposted to the top.');
    }

    /**
     * Boost a Product (Paid Feature)
     * Highlights and keeps at the top for e.g. 72 hours.
     */
    public function boost(Request $request, Product $product)
    {
        $user = $request->user();

        // Authorization
        if ($product->post->user_id !== $user->id) {
            return $this->error('Unauthorized.', 403);
        }

        $request->validate([
            'duration_hours' => 'required|integer|in:24,48,72',
            'amount'         => 'required|numeric|min:500', // FCFA
            'payment_method' => 'required|string',
        ]);

        // Logic: Simulate payment then apply boost
        DB::transaction(function() use ($product, $request) {
            // 1. Mark as boosted
            $product->update([
                'is_boosted'      => true,
                'last_boosted_at' => now(),
                'boost_expiry'    => now()->addHours($request->duration_hours),
            ]);

            // 2. Log boost payment (Stub)
            \App\Models\Payment::create([
                'order_id'       => null, // Boosts don't have an order
                'amount'         => $request->amount,
                'status'         => 'success',
                'type'           => 'boost',
                'payment_method' => $request->payment_method,
            ]);
        });

        return $this->success($product->fresh(), "Product boosted for {$request->duration_hours} hours!");
    }
}
