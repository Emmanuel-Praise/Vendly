<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\DB;

class EscrowTransaction extends Model
{
    protected $fillable = [
        'order_id',
        'held_amount',
        'released',
        'released_at',
    ];

    protected $casts = [
        'released'     => 'boolean',
        'released_at'  => 'datetime',
        'held_amount'  => 'float',
    ];

    // ── Relationships ─────────────────────────────────────────────────────────
    public function order()
    {
        return $this->belongsTo(Order::class);
    }

    // ── Helpers ───────────────────────────────────────────────────────────────

    /**
     * Mark escrow as released (platform releases funds to seller).
     */
    public function release(): bool
    {
        if ($this->released) {
            return false; // already released
        }

        $this->update([
            'released'    => true,
            'released_at' => now(),
        ]);

        return true;
    }
}
