<?php

// ─────────────────────────────────────────────────────────────
// FILE: app/Http/Controllers/Api/V1/QuestionStatsController.php
// ─────────────────────────────────────────────────────────────

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Models\Question;
use App\Services\{QuestionService, QuestionCacheService};
use Illuminate\Http\Request;
use Illuminate\Http\JsonResponse;

class QuestionStatsController extends Controller
{
    public function __construct(
        private QuestionService $questionService
    ) {}

    /**
     * GET /api/v1/questions-stats
     * Dashboard summary stats.
     */
    public function index(): JsonResponse
    {
        $stats = QuestionCacheService::stats();

        return response()->json(['data' => $stats]);
    }

    /**
     * GET /api/v1/questions-stats/aggregations
     * Counts by type, difficulty, status for filter sidebar.
     *
     * Query params: subject_id (optional, to scope counts)
     */
    public function aggregations(Request $request): JsonResponse
    {
        $subjectId = $request->integer('subject_id') ?: null;
        $data = QuestionCacheService::aggregations($subjectId);

        return response()->json(['data' => $data]);
    }

    /**
     * POST /api/v1/questions-search
     * Advanced search for exam paper generation.
     *
     * Body:
     * {
     *   "criteria": [
     *     { "subject_id": 1, "topic_id": 3, "type": "mcq", "difficulty": "easy", "count": 10 },
     *     { "subject_id": 1, "type": "long_answer", "difficulty": "hard", "count": 3 }
     *   ],
     *   "exclude_ids": [101, 102],
     *   "randomize": true
     * }
     */
    public function advancedSearch(Request $request): JsonResponse
    {
        $request->validate([
            'criteria'             => 'required|array|min:1|max:20',
            'criteria.*.subject_id' => 'required|exists:subjects,id',
            'criteria.*.topic_id'  => 'nullable|exists:topics,id',
            'criteria.*.type'      => 'nullable|string',
            'criteria.*.difficulty' => 'nullable|string',
            'criteria.*.tags'      => 'nullable|string',
            'criteria.*.count'     => 'required|integer|min:1|max:100',
            'exclude_ids'          => 'nullable|array',
            'exclude_ids.*'        => 'integer',
            'randomize'            => 'nullable|boolean',
        ]);

        $excludeIds = $request->input('exclude_ids', []);
        $randomize = $request->boolean('randomize', true);
        $results = [];
        $totalMarks = 0;

        foreach ($request->criteria as $index => $criteria) {
            $query = Question::query()
                ->approved()
                ->forSubject($criteria['subject_id']);

            if (!empty($criteria['topic_id'])) {
                $query->forTopic($criteria['topic_id']);
            }
            if (!empty($criteria['type'])) {
                $query->ofType($criteria['type']);
            }
            if (!empty($criteria['difficulty'])) {
                $query->ofDifficulty($criteria['difficulty']);
            }
            if (!empty($criteria['tags'])) {
                $query->withTags(explode(',', $criteria['tags']));
            }
            if (!empty($excludeIds)) {
                $query->whereNotIn('id', $excludeIds);
            }

            if ($randomize) {
                $query->inRandomOrder();
            }

            $questions = $query
                ->with(['subject:id,name', 'topic:id,name', 'options', 'blanks', 'matchPairs', 'expectedAnswer'])
                ->limit($criteria['count'])
                ->get();

            // Track used IDs to prevent duplicates across criteria
            $usedIds = $questions->pluck('id')->toArray();
            $excludeIds = array_merge($excludeIds, $usedIds);

            $groupMarks = $questions->sum('marks');
            $totalMarks += $groupMarks;

            $results[] = [
                'criteria_index' => $index,
                'requested'      => $criteria['count'],
                'found'          => $questions->count(),
                'marks_subtotal' => (float) $groupMarks,
                'questions'      => $questions,
            ];
        }

        return response()->json([
            'data' => [
                'groups'      => $results,
                'total_marks' => (float) $totalMarks,
                'total_questions' => collect($results)->sum('found'),
            ],
        ]);
    }

    /**
     * GET /api/v1/questions/{question}/performance
     * How students performed on this question.
     */
    public function performance(Question $question): JsonResponse
    {
        $total = $question->times_used;
        $correct = $question->times_correct;
        $incorrect = $question->times_incorrect;
        $skipped = $total - $correct - $incorrect;

        return response()->json([
            'data' => [
                'question_id'    => $question->id,
                'times_used'     => $total,
                'times_correct'  => $correct,
                'times_incorrect' => $incorrect,
                'times_skipped'  => max(0, $skipped),
                'accuracy_rate'  => $total > 0
                    ? round(($correct / $total) * 100, 1)
                    : null,
                'avg_time_sec'   => $question->avg_time_sec,
                'difficulty_rating' => $this->calculateDifficultyRating($total, $correct),
            ],
        ]);
    }

    /**
     * Calculate perceived difficulty from actual student performance.
     * Returns: easy (>70% correct), medium (40-70%), hard (<40%)
     */
    private function calculateDifficultyRating(int $total, int $correct): ?string
    {
        if ($total < 10) return null; // not enough data

        $rate = ($correct / $total) * 100;

        return match (true) {
            $rate >= 70 => 'easy',
            $rate >= 40 => 'medium',
            default     => 'hard',
        };
    }
}

