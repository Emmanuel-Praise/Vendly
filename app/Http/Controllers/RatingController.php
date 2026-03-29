<?php

namespace App\Http\Controllers;

use App\Models\Order;
use App\Models\Rating;
use Illuminate\Http\Request;
use App\Traits\ApiResponse;

class RatingController extends Controller
{
    use ApiResponse;
    // ──────────────────────────────────────────────────────────────────────────
    // STORE RATING
    // ──────────────────────────────────────────────────────────────────────────

    /**
     * POST /api/orders/{order}/rate
     * Only the buyer can rate, only after the order is completed, once per order.
     */
    public function store(Request $request, Order $order)
    {
        $user = $request->user();

        if ($order->buyer_id !== $user->id) {
            return $this->error('Only the buyer can rate this order.', 403);
        }

        if ($order->status !== Order::STATUS_COMPLETED) {
            return $this->error('You can only rate completed orders.', 422);
        }

        // One rating per order
        if ($order->rating()->exists()) {
            return $this->error('You have already rated this order.', 422);
        }

        $data = $request->validate([
            'rating'  => 'required|integer|min:1|max:5',
            'comment' => 'nullable|string|max:1000',
        ]);

        $rating = Rating::create([
            'order_id'  => $order->id,
            'buyer_id'  => $user->id,
            'seller_id' => $order->seller_id,
            'rating'    => $data['rating'],
            'comment'   => $data['comment'] ?? null,
        ]);

        return $this->success($rating->load(['buyer', 'seller']), 'Rating submitted successfully', 201);
    }

    // ──────────────────────────────────────────────────────────────────────────
    // SELLER RATINGS
    // ──────────────────────────────────────────────────────────────────────────

    /**
     * GET /api/sellers/{user}/ratings
     * Returns paginated ratings and average score for a seller.
     */
    public function sellerRatings(Request $request, $userId)
    {
        $ratings = Rating::forSeller($userId)
            ->with(['buyer', 'order'])
            ->latest()
            ->paginate(20);

        $average = Rating::forSeller($userId)->avg('rating');

        return $this->success([
            'average_rating' => $average ? round($average, 2) : null,
            'total_ratings'  => Rating::forSeller($userId)->count(),
            'ratings'        => $ratings,
        ]);
    }
}
