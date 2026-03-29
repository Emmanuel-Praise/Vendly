<?php

namespace App\Http\Controllers;

use App\Models\Order;
use App\Models\Product;
use App\Models\Post;
use Illuminate\Http\Request;
use App\Traits\ApiResponse;
use Illuminate\Support\Facades\DB;

class SellerDashboardController extends Controller
{
    use ApiResponse;

    /**
     * Get Seller Stats
     */
    public function stats(Request $request)
    {
        $userId = $request->user()->id;

        $productIds = Product::whereHas('post', function ($q) use ($userId) {
            $q->where('user_id', $userId);
        })->pluck('id');

        $activeListings = Product::whereIn('id', $productIds)->where('quantity', '>', 0)->count();

        // Total Views (Sum of views on all posts belonging to the seller)
        $totalViews = \App\Models\UserView::whereIn('post_id', function($q) use ($userId) {
            $q->select('id')->from('posts')->where('user_id', $userId);
        })->count();

        // Orders Stats
        $orders = Order::where('seller_id', $userId)->get();
        
        $totalOrders = $orders->count();
        $completedOrders = $orders->where('status', Order::STATUS_COMPLETED)->count();
        
        // Earnings Calculation (only completed orders)
        $totalEarnings = Order::where('seller_id', $userId)
            ->where('status', Order::STATUS_COMPLETED)
            ->sum('total_price');

        // Recent Orders
        $recentOrders = Order::with(['product.post', 'buyer'])
            ->where('seller_id', $userId)
            ->latest()
            ->limit(5)
            ->get();

        return $this->success([
            'stats' => [
                'active_listings'  => $activeListings,
                'total_views'      => $totalViews,
                'total_orders'     => $totalOrders,
                'completed_orders' => $completedOrders,
                'total_earnings'   => round($totalEarnings, 2),
            ],
            'recent_orders' => $recentOrders,
        ]);
    }

    /**
     * Get Seller's Active Products
     */
    public function activeProducts(Request $request)
    {
        $userId = $request->user()->id;

        $products = Product::with(['post.images'])
            ->whereHas('post', function ($q) use ($userId) {
                $q->where('user_id', $userId);
            })
            ->latest()
            ->paginate(20);

        return $this->success($products);
    }
}
