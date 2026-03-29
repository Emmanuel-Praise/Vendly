<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use App\Traits\ApiResponse;

class ProfileController extends Controller
{
    use ApiResponse;
    public function show(Request $request)
    {
        $user = $request->user()->load('posts.product', 'reviews');
        $stats = [
            'posts_count' => $user->posts->count(),
            'sales_count' => $user->posts->where('type', 'product')->count(),
            'average_rating' => $user->reviews->avg('rating') ?? 0,
            'followers_count' => $user->followers()->count(),
            'following_count' => $user->following()->count(),
        ];

        return $this->success([
            'user' => $user,
            'stats' => $stats
        ]);
    }

    public function update(Request $request)
    {
        $request->validate([
            'name'           => 'nullable|string|max:255|min:2',
            'phone'          => 'nullable|string|regex:/^[0-9+\-\s]*$/|max:20',
            'location'       => 'nullable|string|max:255',
            'avatar'         => 'nullable|file|image|mimes:jpeg,png,jpg,gif|max:2048',
            'shop_latitude'  => 'nullable|numeric|between:-90,90',
            'shop_longitude' => 'nullable|numeric|between:-180,180',
            'has_shop'       => 'nullable|boolean',
            'is_private'     => 'nullable|boolean',
            'show_location'  => 'nullable|boolean',
        ]);

        $user = $request->user();
        
        $updateData = [];
        if ($request->has('name')) $updateData['name'] = $request->name;
        if ($request->has('phone')) $updateData['phone'] = $request->phone;
        if ($request->has('location')) $updateData['location'] = $request->location;
        if ($request->has('shop_latitude')) $updateData['shop_latitude'] = $request->shop_latitude;
        if ($request->has('shop_longitude')) $updateData['shop_longitude'] = $request->shop_longitude;
        if ($request->has('has_shop')) $updateData['has_shop'] = $request->boolean('has_shop');
        if ($request->has('is_private')) $updateData['is_private'] = $request->boolean('is_private');
        if ($request->has('show_location')) $updateData['show_location'] = $request->boolean('show_location');
        
        if (!empty($updateData)) {
            $user->update($updateData);
        }

        if ($request->hasFile('avatar')) {
            $path = $request->file('avatar')->store('avatars', 'public');
            $user->avatar = asset('storage/' . $path);
            $user->save();
        }

        return $this->success($user, 'Profile updated successfully');
    }

    public function history(Request $request)
    {
        $user = $request->user();
        $history = \App\Models\UserView::with('post.user')
            ->where('user_id', $user->id)
            ->latest()
            ->paginate(20);

        return $this->success($history);
    }

    public function toggleFollow(Request $request, \App\Models\User $user)
    {
        $follower = $request->user();
        if (!$follower) {
            return $this->error('Please login to follow users', 401);
        }
        
        if ($follower->id === $user->id) {
            return response()->json(['message' => 'You cannot follow yourself'], 400);
        }

        $result = $follower->following()->toggle($user->id);
        $isFollowing = count($result['attached']) > 0;

        return $this->success([
            'is_following' => $isFollowing,
        ], $isFollowing ? 'Followed successfully' : 'Unfollowed successfully');
    }
}
