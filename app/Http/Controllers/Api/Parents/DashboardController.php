<?php

namespace App\Http\Controllers\Api\Parents;

use App\Http\Controllers\Controller;
use App\Models\{Quiz, PracticeSet, QuizAttempt};
use Illuminate\Http\Request;
use Illuminate\Http\JsonResponse;

class DashboardController extends Controller
{
    public function index(Request $request): JsonResponse
    {
        $user = $request->user();

        // ── Children linked to this parent ──
        $children = $user->children()->with('activeSubscription.plan:id,name')->get(
            ['id', 'name', 'email', 'avatar']
        );

        // ── Per-child stats ──
        $childrenStats = $children->map(function ($child) {
            $attempts = QuizAttempt::where('user_id', $child->id)
                ->whereIn('status', ['completed', 'submitted', 'auto_submitted'])
                ->selectRaw('
                    COUNT(*)                  AS total,
                    SUM(is_passed)            AS passed,
                    ROUND(AVG(percentage),1)  AS avg_score,
                    ROUND(MAX(percentage),1)  AS best_score
                ')
                ->first();

            $recent = QuizAttempt::where('user_id', $child->id)
                ->with('quiz:id,title')
                ->whereIn('status', ['completed', 'submitted', 'auto_submitted'])
                ->latest('submitted_at')
                ->limit(3)
                ->get(['id', 'quiz_id', 'final_score', 'percentage', 'is_passed', 'submitted_at']);

            return [
                'id'         => $child->id,
                'name'       => $child->name,
                'email'      => $child->email,
                'avatar_url' => $child->avatar_url,
                'subscription' => $child->activeSubscription ? [
                    'plan'       => $child->activeSubscription->plan->name ?? null,
                    'expires_at' => $child->activeSubscription->expires_at?->toDateString(),
                ] : null,
                'quiz_stats' => [
                    'total_attempts' => (int)   ($attempts->total     ?? 0),
                    'passed'         => (int)   ($attempts->passed    ?? 0),
                    'avg_score'      => (float) ($attempts->avg_score ?? 0),
                    'best_score'     => (float) ($attempts->best_score ?? 0),
                ],
                'recent_attempts' => $recent,
            ];
        });

        // ── Available content (for parent awareness) ──
        $availableQuizzes      = Quiz::published()->public()->count();
        $availablePracticeSets = PracticeSet::published()->count();

        return response()->json([
            'data' => [
                'user' => [
                    'id'         => $user->id,
                    'name'       => $user->name,
                    'email'      => $user->email,
                    'avatar_url' => $user->avatar_url,
                ],
                'children'              => $childrenStats,
                'available_quizzes'     => $availableQuizzes,
                'available_practice_sets' => $availablePracticeSets,
            ],
        ]);
    }
}
