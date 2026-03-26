<?php


// ============================================================
// ─── app/Models/QuizAttempt.php ─────────────────────────────
// ============================================================

namespace App\Models;

use App\Enums\AttemptStatus;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\{BelongsTo, HasMany};

class QuizAttempt extends Model
{
    protected $fillable = [
        'quiz_id', 'user_id', 'schedule_id', 'attempt_number', 'status',
        'started_at', 'submitted_at', 'time_spent_sec', 'time_allowed_sec',
        'total_marks', 'marks_obtained', 'negative_marks_total', 'final_score',
        'percentage', 'is_passed', 'rank',
        'total_questions', 'attempted_count', 'correct_count',
        'incorrect_count', 'skipped_count',
        'question_order', 'ip_address', 'user_agent',
    ];

    protected $casts = [
        'status'         => AttemptStatus::class,
        'started_at'     => 'datetime',
        'submitted_at'   => 'datetime',
        'is_passed'      => 'boolean',
        'question_order' => 'array',
        'marks_obtained' => 'decimal:2',
        'final_score'    => 'decimal:2',
        'percentage'     => 'decimal:2',
    ];

    public function quiz(): BelongsTo { return $this->belongsTo(Quiz::class); }
    public function user(): BelongsTo { return $this->belongsTo(User::class); }
    public function schedule(): BelongsTo { return $this->belongsTo(QuizSchedule::class, 'schedule_id'); }

    public function answers(): HasMany
    {
        return $this->hasMany(QuizAttemptAnswer::class, 'attempt_id');
    }

    public function isInProgress(): bool { return $this->status === AttemptStatus::InProgress; }
    public function isCompleted(): bool { return $this->status === AttemptStatus::Completed; }

    public function remainingSeconds(): int
    {
        if (!$this->time_allowed_sec || !$this->started_at) return 0;
        $elapsed = now()->diffInSeconds($this->started_at);
        return max(0, $this->time_allowed_sec - $elapsed);
    }

    public function isTimeUp(): bool
    {
        return $this->time_allowed_sec && $this->remainingSeconds() <= 0;
    }
}
