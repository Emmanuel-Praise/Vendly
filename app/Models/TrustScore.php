<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class TrustScore extends Model
{
    protected $fillable = [
        'user_id',
        'score',
    ];

    protected $casts = [
        'score' => 'integer',
    ];

    // ── Relationships ─────────────────────────────────────────────────────────
    public function user()
    {
        return $this->belongsTo(User::class);
    }

    // ── Static helpers ────────────────────────────────────────────────────────

    /**
     * Ensure a trust score record exists for the user (lazily initialize to 100).
     */
    public static function ensureExists(int $userId): self
    {
        return self::firstOrCreate(
            ['user_id' => $userId],
            ['score'   => 100]
        );
    }

    /**
     * Deduct trust points from a user (score floor: 0).
     */
    public static function deduct(int $userId, int $points): void
    {
        $record = self::ensureExists($userId);
        $record->score = max(0, $record->score - $points);
        $record->save();
    }

    /**
     * Add trust points to a user.
     */
    public static function add(int $userId, int $points): void
    {
        $record = self::ensureExists($userId);
        $record->score += $points;
        $record->save();
    }
}
