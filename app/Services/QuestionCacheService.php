<?php


// ─────────────────────────────────────────────────────────────
// FILE: app/Services/QuestionCacheService.php
// ─────────────────────────────────────────────────────────────

namespace App\Services;

use App\Models\{Question, Subject, Topic, Tag};
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Log;

class QuestionCacheService
{
    // ── HELPERS ──

    /**
     * Check if caching is enabled in config.
     */
    private static function enabled(): bool
    {
        return config('questionbank.cache.enabled', true);
    }

    /**
     * Get TTL for a cache type.
     */
    private static function ttl(string $type): int
    {
        return config("questionbank.cache.ttl.{$type}", 300);
    }

    /**
     * Build prefixed cache key.
     */
    private static function key(string $key): string
    {
        $prefix = config('questionbank.cache.prefix', 'qbank');
        return "{$prefix}:{$key}";
    }

    /**
     * Cache wrapper — if caching disabled, always runs the callback directly.
     */
    private static function remember(string $key, string $ttlType, callable $callback)
    {
        if (!self::enabled()) {
            return $callback();
        }

        try {
            return Cache::remember(
                self::key($key),
                self::ttl($ttlType),
                $callback
            );
        } catch (\Throwable $e) {
            // If cache fails (Redis down etc.), fall back to direct query
            Log::warning("Cache read failed for key [{$key}]: {$e->getMessage()}");
            return $callback();
        }
    }

    /**
     * Safely forget a cache key.
     */
    private static function forget(string $key): void
    {
        try {
            Cache::forget(self::key($key));
        } catch (\Throwable $e) {
            Log::warning("Cache forget failed for key [{$key}]: {$e->getMessage()}");
        }
    }


    // ════════════════════════════════════════════════════════
    // SUBJECT CACHE
    // ════════════════════════════════════════════════════════

    /**
     * Get all active subjects (cached).
     */
    public static function subjects(): \Illuminate\Support\Collection
    {
        return self::remember('subjects:active', 'subjects', function () {
            return Subject::where('is_active', true)
                ->orderBy('sort_order')
                ->get(['id', 'name', 'code', 'description', 'icon_url', 'sort_order']);
        });
    }

    /**
     * Get a single subject with topic tree (cached).
     */
    public static function subject(int $subjectId): ?Subject
    {
        return self::remember("subjects:{$subjectId}", 'subjects', function () use ($subjectId) {
            return Subject::withCount('questions')
                ->with(['rootTopics' => function ($q) {
                    $q->withCount('questions')
                      ->with(['allChildren' => function ($q2) {
                          $q2->withCount('questions');
                      }]);
                }])
                ->find($subjectId);
        });
    }

    /**
     * Flush subject caches.
     */
    public static function flushSubjects(): void
    {
        self::forget('subjects:active');
        // Also flush individual subject caches
        Subject::pluck('id')->each(function ($id) {
            self::forget("subjects:{$id}");
        });

        Log::info('Cache flushed: subjects');
    }


    // ════════════════════════════════════════════════════════
    // TOPIC CACHE
    // ════════════════════════════════════════════════════════

    /**
     * Get topics for a subject — flat list (cached).
     */
    public static function topicsForSubject(int $subjectId): \Illuminate\Support\Collection
    {
        return self::remember("topics:subject:{$subjectId}:flat", 'topics', function () use ($subjectId) {
            return Topic::where('subject_id', $subjectId)
                ->where('is_active', true)
                ->withCount('questions')
                ->orderBy('depth')
                ->orderBy('sort_order')
                ->get(['id', 'name', 'code', 'parent_topic_id', 'depth', 'sort_order']);
        });
    }

    /**
     * Get topics for a subject — tree structure (cached).
     */
    public static function topicTreeForSubject(int $subjectId): \Illuminate\Support\Collection
    {
        return self::remember("topics:subject:{$subjectId}:tree", 'topics', function () use ($subjectId) {
            return Topic::where('subject_id', $subjectId)
                ->where('is_active', true)
                ->whereNull('parent_topic_id')
                ->withCount('questions')
                ->with(['allChildren' => function ($q) {
                    $q->withCount('questions')->orderBy('sort_order');
                }])
                ->orderBy('sort_order')
                ->get();
        });
    }

    /**
     * Flush topic caches for a subject.
     */
    public static function flushTopics(int $subjectId): void
    {
        self::forget("topics:subject:{$subjectId}:flat");
        self::forget("topics:subject:{$subjectId}:tree");

        // Also flush parent subject cache since topic counts changed
        self::forget("subjects:{$subjectId}");

        Log::info("Cache flushed: topics for subject #{$subjectId}");
    }


    // ════════════════════════════════════════════════════════
    // TAG CACHE
    // ════════════════════════════════════════════════════════

    /**
     * Get all tags with question counts (cached).
     */
    public static function tags(?string $category = null): \Illuminate\Support\Collection
    {
        $key = $category ? "tags:category:{$category}" : 'tags:all';

        return self::remember($key, 'tags', function () use ($category) {
            $query = Tag::withCount('questions');

            if ($category) {
                $query->where('category', $category);
            }

            return $query->orderBy('name')->get(['id', 'name', 'slug', 'category']);
        });
    }

    /**
     * Flush tag caches.
     */
    public static function flushTags(): void
    {
        self::forget('tags:all');
        // Flush category-specific caches
        Tag::distinct('category')->pluck('category')->filter()->each(function ($cat) {
            self::forget("tags:category:{$cat}");
        });

        Log::info('Cache flushed: tags');
    }


    // ════════════════════════════════════════════════════════
    // QUESTION CACHE
    // ════════════════════════════════════════════════════════

    /**
     * Get a single question with all relations (cached).
     * Used heavily in exam mode.
     */
    public static function question(int $questionId): ?Question
    {
        return self::remember("question:{$questionId}", 'question', function () use ($questionId) {
            $question = Question::with([
                'subject:id,name,code',
                'topic:id,name,code',
                'tags:id,name,slug',
                'options',
                'blanks',
                'matchPairs',
                'expectedAnswer',
                'creator:id,name',
                'reviewer:id,name',
            ])->find($questionId);

            return $question;
        });
    }

    /**
     * Flush cache for a single question.
     */
    public static function flushQuestion(int $questionId): void
    {
        self::forget("question:{$questionId}");

        // Also flush stats and aggregations since counts changed
        self::flushStats();

        Log::info("Cache flushed: question #{$questionId}");
    }


    // ════════════════════════════════════════════════════════
    // STATS & AGGREGATION CACHE
    // ════════════════════════════════════════════════════════

    /**
     * Dashboard stats (cached).
     */
    public static function stats(): array
    {
        return self::remember('stats:dashboard', 'stats', function () {
            return app(QuestionService::class)->getStats();
        });
    }

    /**
     * Filter aggregations — counts by type/difficulty/status (cached).
     * Optionally scoped to a subject.
     */
    public static function aggregations(?int $subjectId = null): array
    {
        $key = 'aggregations:' . ($subjectId ?? 'all');

        return self::remember($key, 'aggregations', function () use ($subjectId) {
            return app(QuestionService::class)->getAggregations(
                $subjectId ? ['subject_id' => $subjectId] : []
            );
        });
    }

    /**
     * Flush stats and aggregation caches.
     */
    public static function flushStats(): void
    {
        self::forget('stats:dashboard');
        self::forget('aggregations:all');

        // Flush per-subject aggregations
        Subject::pluck('id')->each(function ($id) {
            self::forget("aggregations:{$id}");
        });

        Log::info('Cache flushed: stats + aggregations');
    }


    // ════════════════════════════════════════════════════════
    // FLUSH ALL
    // ════════════════════════════════════════════════════════

    /**
     * Nuclear option — flush everything.
     * Use after bulk imports or major data changes.
     */
    public static function flushAll(): void
    {
        self::flushSubjects();
        self::flushTags();
        self::flushStats();

        // Flush all individual question caches
        Question::pluck('id')->chunk(500)->each(function ($chunk) {
            $chunk->each(function ($id) {
                self::forget("question:{$id}");
            });
        });

        // Flush all topic caches
        Subject::pluck('id')->each(function ($id) {
            self::flushTopics($id);
        });

        Log::info('Cache flushed: ALL question bank caches');
    }
}
