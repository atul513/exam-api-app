<?php

// ============================================================
// ─── app/Models/QuizLeaderboard.php ─────────────────────────
// ============================================================

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class QuizLeaderboard extends Model
{
    protected $table = 'quiz_leaderboard';

    protected $fillable = [
        'quiz_id', 'user_id', 'attempt_id',
        'final_score', 'percentage', 'time_spent_sec',
        'correct_count', 'rank',
    ];

    protected $casts = [
        'final_score' => 'decimal:2',
        'percentage'  => 'decimal:2',
    ];

    public function quiz(): BelongsTo { return $this->belongsTo(Quiz::class); }
    public function user(): BelongsTo { return $this->belongsTo(User::class); }
    public function attempt(): BelongsTo { return $this->belongsTo(QuizAttempt::class, 'attempt_id'); }
}