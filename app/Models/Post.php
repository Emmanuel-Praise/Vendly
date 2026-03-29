<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Post extends Model
{
    protected $fillable = [
        'user_id',
        'type',
        'content_text',
        'media_url',
        'audio_url',
        'price',
        'location',
    ];

    public function user()
    {
        return $this->belongsTo(User::class);
    }

    public function product()
    {
        return $this->hasOne(Product::class);
    }

    public function images()
    {
        return $this->hasMany(PostImage::class);
    }

    public function likes()
    {
        return $this->hasMany(Like::class);
    }

    public function comments()
    {
        return $this->hasMany(Comment::class);
    }

    public function views()
    {
        return $this->hasMany(UserView::class);
    }

    public function getMainImageAttribute()
    {
        $main = $this->images()->where('is_main', true)->first();
        if ($main) return $main->image_url;
        
        $first = $this->images()->first();
        if ($first) return $first->image_url;

        return $this->media_url;
    }

    public function getIsLikedAttribute()
    {
        $user = auth()->user();
        if (!$user) return false;
        return $this->likes()->where('user_id', $user->id)->exists();
    }

    protected $withCount = ['likes', 'comments', 'views'];

    protected $appends = ['main_image', 'is_liked'];
}
