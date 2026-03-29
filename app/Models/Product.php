<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Product extends Model
{
    protected $fillable = [
        'post_id',
        'category',
        'price',
        'availability',
        'quantity',
        'stock_reserved',
        'latitude',
        'longitude',
        'city_area',
        'is_boosted',
        'last_boosted_at',
        'boost_expiry',
    ];

    protected $casts = [
        'is_boosted' => 'boolean',
        'last_boosted_at' => 'datetime',
        'boost_expiry' => 'datetime',
    ];

    protected $appends = ['is_favorited', 'has_ordered'];

    public function getIsFavoritedAttribute()
    {
        if (!auth()->check()) return false;
        return $this->favorites()->where('user_id', auth()->id())->exists();
    }

    public function getHasOrderedAttribute()
    {
        if (!auth()->check()) return false;
        return $this->orders()
            ->where('buyer_id', auth()->id())
            ->whereIn('status', ['pending', 'paid', 'completed'])
            ->exists();
    }


    public function post()
    {
        return $this->belongsTo(Post::class);
    }

    public function orders()
    {
        return $this->hasMany(Order::class);
    }

    /**
     * Number of units currently available for new orders.
     */
    public function availableStock(): int
    {
        return max(0, $this->quantity - $this->stock_reserved);
    }

    /**
     * Scope for boosted products.
     */
    public function scopeBoosted($query)
    {
        return $query->where('is_boosted', true)
                     ->where('boost_expiry', '>', now());
    }

    /**
     * Scope for nearby products using Haversine formula.
     */
    public function scopeNearby($query, $lat, $lng, $radius = 50)
    {
        $haversine = "(6371 * acos(cos(radians(?)) * cos(radians(latitude)) * cos(radians(longitude) - radians(?)) + sin(radians(?)) * sin(radians(latitude))))";
        
        return $query->select('*')
            ->selectRaw("$haversine AS distance", [$lat, $lng, $lat])
            ->whereRaw("$haversine <= ?", [$lat, $lng, $lat, $radius])
            ->orderBy('distance');
    }

    public function favorites()
    {
        return $this->hasMany(Favorite::class);
    }

    public function favoritedBy()
    {
        return $this->belongsToMany(User::class, 'favorites');
    }
}
