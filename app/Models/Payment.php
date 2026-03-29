<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Payment extends Model
{
    protected $fillable = [
        'order_id',
        'amount',
        'type',
        'status',
        'refunded',
        'refunded_at',
        'reference',
        'gateway_response',
    ];

    protected $casts = [
        'refunded'         => 'boolean',
        'refunded_at'      => 'datetime',
        'amount'           => 'float',
        'gateway_response' => 'array',
    ];

    // ── Relationships ─────────────────────────────────────────────────────────
    public function order()
    {
        return $this->belongsTo(Order::class);
    }

    // ── Helpers ───────────────────────────────────────────────────────────────
    public function isRefunded(): bool
    {
        return $this->refunded === true;
    }

    public function isSuccessful(): bool
    {
        return $this->status === 'success';
    }
}
