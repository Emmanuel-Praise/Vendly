<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class ProductController extends Controller
{
    public function index(Request $request)
    {
        $query = \App\Models\Product::with(['post.user']);
        $driver = DB::connection()->getDriverName();

        if ($request->has('category')) {
            $cat = $request->category;
            $mapping = [
                'Foodstuffs' => ['Foodstuffs', 'Vegetables', 'Fruits', 'Grains'],
                'Machinery'  => ['Machinery', 'Equipment', 'Tools'],
                'Devices'    => ['Devices', 'Agri-tech', 'Electronics'],
            ];

            if (isset($mapping[$cat])) {
                $query->whereIn('category', $mapping[$cat]);
            } else {
                $query->where('category', $cat);
            }
        }

        if ($request->has('search')) {
            $search = $request->search;
            $query->whereHas('post', function($q) use ($search, $driver) {
                if ($driver === 'mysql') {
                    $q->whereRaw("MATCH(content_text, location) AGAINST(? IN BOOLEAN MODE)", [$search . '*']);
                } else {
                    $searchTerm = strtolower($search);
                    $q->where(function($sub) use ($searchTerm) {
                        $sub->whereRaw("LOWER(content_text) LIKE ?", ["%{$searchTerm}%"])
                            ->orWhereRaw("LOWER(location) LIKE ?", ["%{$searchTerm}%"]);
                    });
                }
            });
        }

        if ($request->has('location')) {
            $query->whereHas('post', function($q) use ($request) {
                $q->where('location', $request->location);
            });
        }

        return response()->json($query->latest()->paginate(20));
    }
}
