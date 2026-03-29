<?php

namespace App\Http\Controllers;

use App\Models\Favorite;
use App\Models\Product;
use Illuminate\Http\Request;
use App\Traits\ApiResponse;

class FavoriteController extends Controller
{
    use ApiResponse;

    /**
     * Toggle Favorite (Add/Remove)
     */
    public function toggle(Request $request, Product $product)
    {
        $user = $request->user();

        $favorite = Favorite::where('user_id', $user->id)
            ->where('product_id', $product->id)
            ->first();

        if ($favorite) {
            $favorite->delete();
            return $this->success(['is_favorite' => false], 'Removed from saved items');
        } else {
            Favorite::create([
                'user_id'    => $user->id,
                'product_id' => $product->id,
            ]);
            return $this->success(['is_favorite' => true], 'Added to saved items');
        }
    }

    /**
     * Get User Favorites
     */
    public function index(Request $request)
    {
        $favorites = $request->user()->favoriteProducts()
            ->with(['post.user', 'post.images'])
            ->latest()
            ->paginate(20);

        return $this->success($favorites);
    }
}
