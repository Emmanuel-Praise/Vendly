<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Order extends Model
{
    protected $fillable = [
        'buyer_id',
        'seller_id',
        'product_id',
        'quantity',
        'total_price',
        'payment_type',
        'status',
        'seller_accepted_at',
    ];

    protected $casts = [
        'seller_accepted_at' => 'datetime',
        'total_price'        => 'float',
    ];

    // ── Status constants ──────────────────────────────────────────────────────
    const STATUS_PENDING   = 'pending';
    const STATUS_PAID      = 'paid';
    const STATUS_COMPLETED = 'completed';
    const STATUS_CANCELLED = 'cancelled';
    const STATUS_REFUNDED  = 'refunded';
    const STATUS_DISPUTED  = 'disputed';

    // ── Relationships ─────────────────────────────────────────────────────────
    public function buyer()
    {
        return $this->belongsTo(User::class, 'buyer_id');
    }

    public function seller()
    {
        return $this->belongsTo(User::class, 'seller_id');
    }

    public function product()
    {
        return $this->belongsTo(Product::class);
    }

    public function payment()
    {
        return $this->hasOne(Payment::class);
    }

    public function escrow()
    {
        return $this->hasOne(EscrowTransaction::class);
    }

    public function rating()
    {
        return $this->hasOne(Rating::class);
    }

    // ── Helpers ───────────────────────────────────────────────────────────────
    public function isOwnedBy(int $userId): bool
    {
        return $this->buyer_id === $userId || $this->seller_id === $userId;
    }

    public function sellerHasAccepted(): bool
    {
        return $this->seller_accepted_at !== null;
    }
}
