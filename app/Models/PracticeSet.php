<?php

// ─── app/Models/PracticeSet.php ─────────────────────────────

namespace App\Models;

use App\Enums\AccessType;
use App\Traits\HasExamSections;
use App\Traits\HasShareLinks;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Relations\{BelongsTo, BelongsToMany, HasMany};
use Illuminate\Support\Str;

class PracticeSet extends Model
{
    use SoftDeletes, HasExamSections, HasShareLinks;

    protected $fillable = [
        'title', 'slug', 'category_id', 'subject_id', 'topic_id',
        'description', 'thumbnail_url', 'access_type', 'price', 'plan_id', 'status',
        'allow_reward_points', 'points_mode', 'points_per_question',
        'show_reward_popup',
        'total_questions', 'created_by',
    ];

    protected $casts = [
        'access_type'          => AccessType::class,
        'allow_reward_points'  => 'boolean',
        'show_reward_popup'    => 'boolean',
        'price'                => 'decimal:2',
    ];

    protected static function booted(): void
    {
        static::creating(function (self $ps) {
            $ps->slug = $ps->slug ?: Str::slug($ps->title) . '-' . Str::random(5);
        });
    }

    // ── Relationships ──

    public function category(): BelongsTo
    {
        return $this->belongsTo(QuizCategory::class, 'category_id');
    }

    public function subject(): BelongsTo
    {
        return $this->belongsTo(Subject::class);
    }

    public function topic(): BelongsTo
    {
        return $this->belongsTo(Topic::class);
    }

    public function creator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    public function practiceSetQuestions(): HasMany
    {
        return $this->hasMany(PracticeSetQuestion::class)->orderBy('sort_order');
    }

    public function questions(): BelongsToMany
    {
        return $this->belongsToMany(Question::class, 'practice_set_questions')
            ->withPivot(['sort_order', 'points_override'])
            ->orderBy('practice_set_questions.sort_order');
    }

    public function progress(): HasMany
    {
        return $this->hasMany(PracticeSetProgress::class);
    }

    // ── Scopes ──

    public function scopePublished(Builder $q): Builder
    {
        return $q->where('status', 'published');
    }

    public function scopeFree(Builder $q): Builder
    {
        return $q->where('access_type', 'free');
    }

    public function scopeForSubject(Builder $q, int $subjectId): Builder
    {
        return $q->where('subject_id', $subjectId);
    }

    public function scopeForTopic(Builder $q, int $topicId): Builder
    {
        return $q->where('topic_id', $topicId);
    }

    // ── Helpers ──

    public function isPublished(): bool
    {
        return $this->status === 'published';
    }

    public function canUserAccess(int $userId): array
    {
        if (!$this->isPublished()) {
            return ['allowed' => false, 'reason' => 'Practice set is not published.'];
        }

        if ($this->access_type === AccessType::Paid) {
            $user = \App\Models\User::find($userId);
            if (!$user || !$user->hasActivePlan($this->plan_id)) {
                return [
                    'allowed'  => false,
                    'reason'   => 'An active subscription is required to access this practice set.',
                    'requires' => 'subscription',
                    'plan_id'  => $this->plan_id,
                ];
            }
        }

        return ['allowed' => true, 'reason' => 'OK'];
    }

    public function recalculateTotals(): void
    {
        $this->update([
            'total_questions' => $this->practiceSetQuestions()->count(),
        ]);
    }

    /**
     * Get effective points for a question in this set.
     */
    public function getPointsForQuestion(PracticeSetQuestion $psq): int
    {
        if (!$this->allow_reward_points) return 0;

        // Per-question override first
        if ($psq->points_override !== null) {
            return $psq->points_override;
        }

        // Manual mode: use set-level points_per_question
        if ($this->points_mode === 'manual') {
            return $this->points_per_question ?? 1;
        }

        // Auto mode: use question's marks from question bank
        return (int) ($psq->question->marks ?? 1);
    }

    /**
     * Get user's progress summary for this set.
     */
    public function getUserProgress(int $userId): array
    {
        $progress = $this->progress()->where('user_id', $userId)->get();
        $total = $this->total_questions;

        return [
            'total_questions' => $total,
            'attempted'       => $progress->count(),
            'correct'         => $progress->where('is_correct', true)->count(),
            'incorrect'       => $progress->where('is_correct', false)->count(),
            'remaining'       => $total - $progress->count(),
            'total_points'    => $progress->sum('points_earned'),
            'completion_pct'  => $total > 0 ? round(($progress->count() / $total) * 100, 1) : 0,
        ];
    }
}
