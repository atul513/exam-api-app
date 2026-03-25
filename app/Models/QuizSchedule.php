<?php

// ============================================================
// ─── app/Models/QuizSchedule.php ────────────────────────────
// ============================================================

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\{BelongsTo, BelongsToMany};

class QuizSchedule extends Model
{
    protected $fillable = [
        'quiz_id', 'title', 'starts_at', 'ends_at',
        'grace_period_min', 'is_active',
    ];

    protected $casts = [
        'starts_at'  => 'datetime',
        'ends_at'    => 'datetime',
        'is_active'  => 'boolean',
    ];

    public function quiz(): BelongsTo { return $this->belongsTo(Quiz::class); }

    public function userGroups(): BelongsToMany
    {
        return $this->belongsToMany(UserGroup::class, 'quiz_schedule_groups', 'schedule_id', 'user_group_id');
    }

    public function isActive(): bool
    {
        return $this->is_active
            && $this->starts_at->lte(now())
            && $this->ends_at->addMinutes($this->grace_period_min)->gte(now());
    }

    public function scopeActive($q)
    {
        return $q->where('is_active', true)
            ->where('starts_at', '<=', now())
            ->where('ends_at', '>=', now());
    }
}


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


// ============================================================
// ─── app/Models/QuizAttemptAnswer.php ───────────────────────
// ============================================================

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class QuizAttemptAnswer extends Model
{
    protected $fillable = [
        'attempt_id', 'question_id', 'quiz_question_id',
        'selected_option_ids', 'text_answer', 'fill_blank_answers', 'match_pairs_answer',
        'is_correct', 'marks_awarded', 'negative_marks',
        'is_manually_graded', 'graded_by', 'grader_feedback',
        'time_spent_sec', 'is_bookmarked', 'visit_count',
    ];

    protected $casts = [
        'selected_option_ids' => 'array',
        'fill_blank_answers'  => 'array',
        'match_pairs_answer'  => 'array',
        'is_correct'          => 'boolean',
        'is_manually_graded'  => 'boolean',
        'is_bookmarked'       => 'boolean',
        'marks_awarded'       => 'decimal:2',
        'negative_marks'      => 'decimal:2',
    ];

    public function attempt(): BelongsTo { return $this->belongsTo(QuizAttempt::class, 'attempt_id'); }
    public function question(): BelongsTo { return $this->belongsTo(Question::class); }
    public function quizQuestion(): BelongsTo { return $this->belongsTo(QuizQuestion::class); }
    public function grader(): BelongsTo { return $this->belongsTo(User::class, 'graded_by'); }

    public function isAnswered(): bool
    {
        return !empty($this->selected_option_ids)
            || !empty($this->text_answer)
            || !empty($this->fill_blank_answers)
            || !empty($this->match_pairs_answer);
    }
}
