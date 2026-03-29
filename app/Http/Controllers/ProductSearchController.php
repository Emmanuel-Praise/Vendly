<?php

namespace App\Http\Controllers;

use App\Models\Product;
use Illuminate\Http\Request;
use App\Traits\ApiResponse;
use Illuminate\Support\Facades\DB;

class ProductSearchController extends Controller
{
    use ApiResponse;

    /**
     * Advanced Search with Filters & Geolocation
     */
    public function search(Request $request)
    {
        $query = Product::with(['post.user', 'post.images'])
            ->leftJoin('posts', 'products.post_id', '=', 'posts.id')
            ->select('products.*');

        // Text Search (Name/Description)
        if ($request->has('q')) {
            $search = $request->q;
            $query->where(function($q) use ($search) {
                $q->where('posts.content_text', 'LIKE', "%{$search}%")
                  ->orWhere('products.category', 'LIKE', "%{$search}%");
            });
        }

        // Category Filter
        if ($request->has('category')) {
            $query->where('products.category', $request->category);
        }

        // Price Range Filter
        if ($request->has('min_price')) {
            $query->where('products.price', '>=', $request->min_price);
        }
        if ($request->has('max_price')) {
            $query->where('products.price', '<=', $request->max_price);
        }

        // Location Filter (City/Area String)
        if ($request->has('location')) {
            $query->where('posts.location', 'LIKE', "%{$request->location}%");
        }

        // Geolocation / Nearby (Lat/Lng + Radius)
        if ($request->has('lat') && $request->has('lng')) {
            $lat = $request->lat;
            $lng = $request->lng;
            $radius = $request->get('radius', 50); // Default 50km

            $haversine = "(6371 * acos(cos(radians(?)) * cos(radians(products.latitude)) * cos(radians(products.longitude) - radians(?)) + sin(radians(?)) * sin(radians(products.latitude))))";
            
            $query->selectRaw("$haversine AS distance", [$lat, $lng, $lat])
                  ->whereRaw("$haversine <= ?", [$lat, $lng, $lat, $radius])
                  ->orderBy('distance');
        } else {
            // Default ordering: Boosted first, then latest
            $query->orderByDesc('is_boosted')
                  ->orderByDesc('products.created_at');
        }

        return $this->success($query->paginate($request->get('per_page', 20)));
    }

    /**
     * Price Discovery / Analytics
     * Based on similar products in the same category or search term.
     */
    public function priceAnalytics(Request $request)
    {
        $request->validate([
            'category' => 'nullable|string',
            'q'        => 'nullable|string',
        ]);

        $query = Product::query();

        if ($request->has('category')) {
            $query->where('category', $request->category);
        }

        if ($request->has('q')) {
            $search = $request->q;
            $query->whereHas('post', function($q) use ($search) {
                $q->where('content_text', 'LIKE', "%{$search}%");
            });
        }

        $stats = $query->selectRaw('
            AVG(price) as average_price,
            MIN(price) as min_price,
            MAX(price) as max_price,
            COUNT(*) as total_listings
        ')->first();

        return $this->success([
            'category'      => $request->category,
            'search_term'   => $request->q,
            'average_price' => round($stats->average_price, 2),
            'min_price'     => $stats->min_price,
            'max_price'     => $stats->max_price,
            'sample_size'   => $stats->total_listings,
        ]);
    }
}
