<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use App\Traits\ApiResponse;

class PostController extends Controller
{
    use ApiResponse;
    public function index(Request $request)
    {
        $user = $request->user();
        $query = \App\Models\Post::with(['user', 'images', 'product']);
        
        if ($request->has('search')) {
            $search = $request->query('search');
            if ($user) {
                \App\Models\UserSearch::create(['user_id' => $user->id, 'search_term' => $search]);
            }
            $driver = DB::connection()->getDriverName();
            $query->where(function($q) use ($search, $driver) {
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
            return $this->success($query->paginate(20));
        }

        if ($user) {
            $seenPostIds = \App\Models\UserView::where('user_id', $user->id)->pluck('post_id')->toArray();
            
            $friendIdsQuery1 = \App\Models\Message::where('sender_id', $user->id)->pluck('receiver_id');
            $friendIdsQuery2 = \App\Models\Message::where('receiver_id', $user->id)->pluck('sender_id');
            $friendIds = $friendIdsQuery1->merge($friendIdsQuery2)->unique()->toArray();

            $friendViewedPostIds = \App\Models\UserView::whereIn('user_id', $friendIds)->pluck('post_id')->toArray();

            if (!empty($seenPostIds)) {
                $query->whereNotIn('id', $seenPostIds);
            }

            if (!empty($friendViewedPostIds) && count($friendViewedPostIds) <= 100) {
                $friendIdsString = implode(',', array_merge([0], $friendViewedPostIds));
                $query->orderByRaw("FIELD(id, $friendIdsString) DESC");
            } elseif (!empty($friendViewedPostIds)) {
                $query->orderByRaw("CASE WHEN id IN (" . implode(',', array_slice($friendViewedPostIds, 0, 100)) . ") THEN 0 ELSE 1 END");
            }
        }
        
        return $this->success($query->latest()->paginate(20));
    }

    public function trackView(Request $request)
    {
        $request->validate(['post_id' => 'required|exists:posts,id']);
        $user = $request->user();
        if ($user) {
            \App\Models\UserView::firstOrCreate([
                'user_id' => $user->id,
                'post_id' => $request->post_id
            ]);
        }
        return $this->success(null, 'View tracked successfully');
    }

    public function store(Request $request)
    {
        $request->validate([
            'main_image_index' => 'nullable|integer',
            'category'         => 'required_if:type,product|string|max:100',
            'price'            => 'required_if:type,product|numeric|min:0',
        ]);

        $post = $request->user()->posts()->create($request->only([
            'type', 'content_text', 'price', 'location'
        ]));

        // Handle single media file (legacy or video)
        if ($request->hasFile('media_file')) {
            $post->media_url = $this->uploadMedia($request->file('media_file'));
        }

        // Handle multiple images
        if ($request->hasFile('media_files')) {
            $files = $request->file('media_files');
            $mainIndex = $request->input('main_image_index', 0);

            foreach ($files as $index => $file) {
                $url = $this->uploadMedia($file);
                if ($url) {
                    $post->images()->create([
                        'image_url' => $url,
                        'is_main' => $index == $mainIndex,
                    ]);
                }
            }
        }

        if ($request->hasFile('audio_file')) {
            $post->audio_url = $this->uploadMedia($request->file('audio_file'), 'audio');
        }

        $post->save();

        if ($request->type === 'product') {
            $post->product()->create([
                'category' => $request->category,
                'price' => $request->price,
            ]);
        }

        return $this->success($post->load('user', 'product', 'images'), 'Post created successfully', 201);
    }

    private function uploadMedia($file, $type = 'auto')
    {
        try {
            if (env('CLOUDINARY_URL') && class_exists('\CloudinaryLabs\CloudinaryLaravel\Facades\Cloudinary')) {
                $result = \CloudinaryLabs\CloudinaryLaravel\Facades\Cloudinary::upload($file->getRealPath(), ['resource_type' => $type]);
                return $result->getSecurePath();
            } else {
                $dir = ($type === 'audio') ? 'audio' : 'media';
                $path = $file->store($dir, 'public');
                return asset('storage/' . $path);
            }
        } catch (\Exception $e) {
            \Log::error('Media upload failed: ' . $e->getMessage());
            return null;
        }
    }

    public function userPosts($userId)
    {
        $posts = \App\Models\Post::with(['user', 'images', 'product'])->where('user_id', $userId)->latest()->paginate(20);
        return $this->success($posts);
    }

    public function toggleLike(\App\Models\Post $post)
    {
        $user = auth()->user();
        if (!$user) {
            return $this->error('Please login to like posts', 401);
        }
        $userId = $user->id;
        $like = $post->likes()->where('user_id', $userId)->first();

        if ($like) {
            $like->delete();
            $status = 'unliked';
        } else {
            $post->likes()->create(['user_id' => $userId]);
            $status = 'liked';
        }

        return $this->success([
            'status' => $status,
            'is_liked' => $status === 'liked',
            'likes_count' => $post->likes()->count(),
        ]);
    }

    public function getComments(\App\Models\Post $post)
    {
        $comments = $post->comments()->with('user')->latest()->paginate(20);
        return $this->success($comments);
    }

    public function addComment(Request $request, \App\Models\Post $post)
    {
        $request->validate(['comment_text' => 'required|string|max:1000']);
        $user = $request->user();
        if (!$user) {
            return $this->error('Please login to comment', 401);
        }

        $comment = $post->comments()->create([
            'user_id' => $user->id,
            'comment_text' => $request->comment_text,
        ]);

        return $this->success($comment->load('user'), 'Comment added successfully', 201);
    }
}
