<?php

namespace App\Models;

use App\Enums\{QuestionType, Difficulty, QuestionStatus};
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Database\Eloquent\Relations\{BelongsTo, BelongsToMany, HasMany, HasOne};
use Illuminate\Database\Eloquent\Builder;

class Question extends Model
{
    use SoftDeletes;

    protected $fillable = [
        'subject_id', 'topic_id', 'type', 'difficulty', 'status',
        'question_text', 'question_media',
        'marks', 'negative_marks', 'time_limit_sec',
        'explanation', 'explanation_media', 'solution_approach',
        'language', 'source', 'created_by', 'reviewed_by',
        'import_batch_id', 'external_id',
    ];

    protected $casts = [
        'type'              => QuestionType::class,
        'difficulty'        => Difficulty::class,
        'status'            => QuestionStatus::class,
        'question_media'    => 'array',
        'explanation_media'  => 'array',
        'marks'             => 'decimal:2',
        'negative_marks'    => 'decimal:2',
    ];

    // ── RELATIONSHIPS ──

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

    public function reviewer(): BelongsTo
    {
        return $this->belongsTo(User::class, 'reviewed_by');
    }

    public function options(): HasMany
    {
        return $this->hasMany(QuestionOption::class)->orderBy('sort_order');
    }

    public function blanks(): HasMany
    {
        return $this->hasMany(QuestionBlank::class)->orderBy('blank_number');
    }

    public function matchPairs(): HasMany
    {
        return $this->hasMany(QuestionMatchPair::class)->orderBy('sort_order');
    }

    public function expectedAnswer(): HasOne
    {
        return $this->hasOne(QuestionExpectedAnswer::class);
    }

    public function tags(): BelongsToMany
    {
        return $this->belongsToMany(Tag::class, 'question_tags');
    }

    public function importBatch(): BelongsTo
    {
        return $this->belongsTo(ImportBatch::class);
    }

    public function auditLogs(): HasMany
    {
        return $this->hasMany(QuestionAuditLog::class)->orderByDesc('created_at');
    }

    // ── SCOPES (chainable filters) ──

    public function scopeOfType(Builder $query, string|array $types): Builder
    {
        return $query->whereIn('type', (array) $types);
    }

    public function scopeOfDifficulty(Builder $query, string|array $levels): Builder
    {
        return $query->whereIn('difficulty', (array) $levels);
    }

    public function scopeOfStatus(Builder $query, string|array $statuses): Builder
    {
        return $query->whereIn('status', (array) $statuses);
    }

    public function scopeForSubject(Builder $query, int $subjectId): Builder
    {
        return $query->where('subject_id', $subjectId);
    }

    public function scopeForTopic(Builder $query, int $topicId): Builder
    {
        return $query->where('topic_id', $topicId);
    }

    public function scopeSearch(Builder $query, string $term): Builder
    {
        return $query->whereRaw(
            'MATCH(question_text) AGAINST(? IN BOOLEAN MODE)',
            [$term . '*']
        );
    }

    public function scopeApproved(Builder $query): Builder
    {
        return $query->where('status', QuestionStatus::Approved);
    }

    public function scopeWithTags(Builder $query, array $tagSlugs): Builder
    {
        return $query->whereHas('tags', function ($q) use ($tagSlugs) {
            $q->whereIn('slug', $tagSlugs);
        });
    }

    // ── HELPERS ──

    public function isOptionBased(): bool
    {
        return $this->type->hasOptions();
    }

    public function correctOptions()
    {
        return $this->options->where('is_correct', true);
    }

    // Load the right child relation based on type
    public function loadTypeSpecificRelations(): self
    {
        return match (true) {
            $this->type->hasOptions()       => $this->load('options'),
            $this->type->hasBlanks()        => $this->load('blanks'),
            $this->type->hasMatchPairs()    => $this->load('matchPairs'),
            $this->type->hasExpectedAnswer()=> $this->load('expectedAnswer'),
            default                         => $this,
        };
    }
}

