<?php

namespace App\Http\Controllers\Api\Teacher;

use App\Http\Controllers\Controller;
use App\Models\{User, Quiz, Question, PracticeSet, QuizAttempt};
use Illuminate\Http\Request;
use Illuminate\Http\JsonResponse;

class DashboardController extends Controller
{
    public function index(Request $request): JsonResponse
    {
        $user   = $request->user();
        $userId = $user->id;

        // ── My content stats ──
        $myQuizzes = Quiz::where('created_by', $userId);
        $myQuizIds = (clone $myQuizzes)->pluck('id');

        $quizStats = [
            'total'     => (clone $myQuizzes)->count(),
            'published' => (clone $myQuizzes)->where('status', 'published')->count(),
            'draft'     => (clone $myQuizzes)->where('status', 'draft')->count(),
        ];

        $questionStats = Question::where('created_by', $userId)
            ->selectRaw('
                COUNT(*)    AS total,
                SUM(CASE WHEN status = "active"   THEN 1 ELSE 0 END) AS active,
                SUM(CASE WHEN status = "inactive" THEN 1 ELSE 0 END) AS inactive
            ')
            ->first();

        $practiceStats = [
            'total'     => PracticeSet::where('created_by', $userId)->count(),
            'published' => PracticeSet::where('created_by', $userId)->where('status', 'published')->count(),
        ];

        // ── Student attempts on my quizzes ──
        $attemptStats = QuizAttempt::whereIn('quiz_id', $myQuizIds)
            ->selectRaw('
                COUNT(*)                 AS total_attempts,
                COUNT(DISTINCT user_id)  AS unique_students,
                SUM(is_passed)           AS total_passed,
                ROUND(AVG(percentage),1) AS avg_score
            ')
            ->first();

        // ── Recent student attempts on my quizzes (last 8) ──
        $recentAttempts = QuizAttempt::whereIn('quiz_id', $myQuizIds)
            ->with([
                'user:id,name,email,avatar',
                'quiz:id,title',
            ])
            ->whereIn('status', ['completed', 'submitted', 'auto_submitted'])
            ->latest('submitted_at')
            ->limit(8)
            ->get(['id', 'quiz_id', 'user_id', 'final_score', 'percentage', 'is_passed', 'submitted_at']);

        // ── My top quizzes by attempt count ──
        $topQuizzes = Quiz::where('created_by', $userId)
            ->withCount('attempts')
            ->orderByDesc('attempts_count')
            ->limit(5)
            ->get(['id', 'title', 'status', 'type']);

        return response()->json([
            'data' => [
                'user' => [
                    'id'         => $user->id,
                    'name'       => $user->name,
                    'email'      => $user->email,
                    'avatar_url' => $user->avatar_url,
                ],
                'quiz_stats'     => $quizStats,
                'question_stats' => [
                    'total'    => (int) ($questionStats->total    ?? 0),
                    'active'   => (int) ($questionStats->active   ?? 0),
                    'inactive' => (int) ($questionStats->inactive ?? 0),
                ],
                'practice_stats'  => $practiceStats,
                'attempt_stats'   => [
                    'total_attempts'    => (int)   ($attemptStats->total_attempts   ?? 0),
                    'unique_students'   => (int)   ($attemptStats->unique_students  ?? 0),
                    'total_passed'      => (int)   ($attemptStats->total_passed     ?? 0),
                    'avg_score'         => (float) ($attemptStats->avg_score        ?? 0),
                ],
                'recent_attempts' => $recentAttempts,
                'top_quizzes'     => $topQuizzes,
            ],
        ]);
    }

    public function students(Request $request): JsonResponse
    {
        $search  = $request->input('search');
        $perPage = (int) $request->input('per_page', 15);

        $students = User::where('role', 'student')
            ->when($search, fn($q) => $q->where(function ($q) use ($search) {
                $q->where('name', 'like', "%{$search}%")
                  ->orWhere('email', 'like', "%{$search}%");
            }))
            ->latest()
            ->paginate($perPage, ['id', 'name', 'email', 'avatar', 'created_at']);

        return response()->json($students);
    }
}
