<?php

// ─── app/Models/UserRewardPoint.php ─────────────────────────

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class UserRewardPoint extends Model
{
    protected $fillable = [
        'user_id', 'source_type', 'source_id', 'question_id',
        'points', 'description',
    ];

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    /**
     * Get total points for a user.
     */
    public static function totalForUser(int $userId): int
    {
        return static::where('user_id', $userId)->sum('points');
    }
}
