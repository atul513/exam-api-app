<?php

// ============================================================
// ─── app/Models/Quiz.php (CORE MODEL) ──────────────────────
// ============================================================

namespace App\Models;

use App\Enums\{QuizType, QuizStatus, AccessType, Visibility};
use App\Traits\HasExamSections;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Database\Eloquent\Relations\{BelongsTo, BelongsToMany, HasMany};
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Str;

class Quiz extends Model
{
    use SoftDeletes, HasExamSections;

    protected $fillable = [
        // Details
        'title', 'slug', 'category_id', 'type', 'access_type',
        'price', 'plan_id', 'description', 'instructions', 'thumbnail_url', 'visibility', 'status',
        // Settings
        'duration_mode', 'total_duration_min',
        'marks_mode', 'fixed_marks_per_question',
        'negative_marking', 'negative_marks_per_question',
        'pass_percentage',
        'shuffle_questions', 'shuffle_options', 'max_attempts',
        'disable_finish_button', 'enable_question_list_view',
        'hide_solutions', 'show_leaderboard',
        'show_result_immediately', 'allow_review_after_submit',
        'auto_submit_on_timeout',
        // Meta
        'language', 'created_by', 'total_marks', 'total_questions',
    ];

    protected $casts = [
        'type'                      => QuizType::class,
        'status'                    => QuizStatus::class,
        'access_type'               => AccessType::class,
        'visibility'                => Visibility::class,
        'price'                     => 'decimal:2',
        'pass_percentage'           => 'decimal:2',
        'fixed_marks_per_question'  => 'decimal:2',
        'negative_marks_per_question' => 'decimal:2',
        'negative_marking'          => 'boolean',
        'shuffle_questions'         => 'boolean',
        'shuffle_options'           => 'boolean',
        'disable_finish_button'     => 'boolean',
        'enable_question_list_view' => 'boolean',
        'hide_solutions'            => 'boolean',
        'show_leaderboard'          => 'boolean',
        'show_result_immediately'   => 'boolean',
        'allow_review_after_submit' => 'boolean',
        'auto_submit_on_timeout'    => 'boolean',
    ];

    protected static function booted(): void
    {
        static::creating(fn(self $q) => $q->slug = $q->slug ?: Str::slug($q->title) . '-' . Str::random(5));
    }

    // ── Relationships ──

    public function category(): BelongsTo
    {
        return $this->belongsTo(QuizCategory::class, 'category_id');
    }

    public function creator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    public function sections(): HasMany
    {
        return $this->hasMany(QuizSection::class)->orderBy('sort_order');
    }

    public function quizQuestions(): HasMany
    {
        return $this->hasMany(QuizQuestion::class)->orderBy('sort_order');
    }

    public function questions(): BelongsToMany
    {
        return $this->belongsToMany(Question::class, 'quiz_questions')
            ->withPivot(['sort_order', 'marks_override', 'negative_marks_override', 'section_id'])
            ->orderBy('quiz_questions.sort_order');
    }

    public function schedules(): HasMany
    {
        return $this->hasMany(QuizSchedule::class)->orderBy('starts_at');
    }

    public function attempts(): HasMany
    {
        return $this->hasMany(QuizAttempt::class);
    }

    public function leaderboard(): HasMany
    {
        return $this->hasMany(QuizLeaderboard::class)->orderBy('rank');
    }

    // ── Scopes ──

    public function scopePublished(Builder $q): Builder
    {
        return $q->where('status', QuizStatus::Published);
    }

    public function scopeOfType(Builder $q, string $type): Builder
    {
        return $q->where('type', $type);
    }

    public function scopePublic(Builder $q): Builder
    {
        return $q->where('visibility', Visibility::Public);
    }

    public function scopeFree(Builder $q): Builder
    {
        return $q->where('access_type', AccessType::Free);
    }

    // ── Helpers ──

    public function isPublished(): bool { return $this->status === QuizStatus::Published; }
    public function isPaid(): bool { return $this->access_type === AccessType::Paid; }
    public function isPrivate(): bool { return $this->visibility === Visibility::Private; }

    public function recalculateTotals(): void
    {
        $questions = $this->quizQuestions()->with('question')->get();

        $totalMarks = $questions->sum(function ($qq) {
            if ($this->marks_mode === 'fixed') {
                return $this->fixed_marks_per_question ?? 1;
            }
            return $qq->marks_override ?? $qq->question->marks ?? 1;
        });

        $this->update([
            'total_marks'     => $totalMarks,
            'total_questions' => $questions->count(),
        ]);
    }

    public function getActiveSchedule(): ?QuizSchedule
    {
        return $this->schedules()
            ->where('is_active', true)
            ->where('starts_at', '<=', now())
            ->where('ends_at', '>=', now())
            ->first();
    }

    public function userAttemptCount(int $userId): int
    {
        return $this->attempts()
            ->where('user_id', $userId)
            ->whereNotIn('status', ['abandoned'])
            ->count();
    }

    public function canUserAttempt(int $userId): array
    {
        if (!$this->isPublished()) {
            return ['allowed' => false, 'reason' => 'Quiz is not published.'];
        }

        // ── Paid access check ──
        if ($this->isPaid()) {
            $user = \App\Models\User::find($userId);
            if (!$user || !$user->hasActivePlan($this->plan_id)) {
                return [
                    'allowed'  => false,
                    'reason'   => 'An active subscription is required to access this quiz.',
                    'requires' => 'subscription',
                    'plan_id'  => $this->plan_id,
                ];
            }
        }

        if ($this->max_attempts) {
            $count = $this->userAttemptCount($userId);
            if ($count >= $this->max_attempts) {
                return ['allowed' => false, 'reason' => "Maximum {$this->max_attempts} attempts reached."];
            }
        }

        // Check if any schedule is active (if schedules exist)
        if ($this->schedules()->exists()) {
            $active = $this->getActiveSchedule();
            if (!$active) {
                return ['allowed' => false, 'reason' => 'No active schedule. Quiz is not available right now.'];
            }
        }

        // Check if user already has in_progress attempt
        $inProgress = $this->attempts()
            ->where('user_id', $userId)
            ->where('status', 'in_progress')
            ->first();
        if ($inProgress) {
            return ['allowed' => true, 'resume_attempt_id' => $inProgress->id, 'reason' => 'Resume existing attempt.'];
        }

        return ['allowed' => true, 'reason' => 'OK'];
    }
}