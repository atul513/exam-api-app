<?php

namespace App\Http\Controllers\Api\Student;

use App\Http\Controllers\Controller;
use App\Models\{Quiz, QuizAttempt, PracticeSet, PracticeSetProgress};
use Illuminate\Http\Request;
use Illuminate\Http\JsonResponse;

class DashboardController extends Controller
{
    public function index(Request $request): JsonResponse
    {
        $user   = $request->user();
        $userId = $user->id;

        // ── Quiz attempt stats ──
        $attemptStats = QuizAttempt::where('user_id', $userId)
            ->whereIn('status', ['completed', 'submitted', 'auto_submitted', 'grading'])
            ->selectRaw('
                COUNT(*)                  AS total_attempts,
                SUM(is_passed)            AS passed,
                ROUND(AVG(percentage),1)  AS avg_score,
                ROUND(MAX(percentage),1)  AS best_score,
                SUM(time_spent_sec)       AS total_time_sec
            ')
            ->first();

        $inProgressCount = QuizAttempt::where('user_id', $userId)
            ->where('status', 'in_progress')
            ->count();

        // ── Practice stats ──
        $practiceStats = PracticeSetProgress::where('user_id', $userId)
            ->selectRaw('
                COUNT(DISTINCT practice_set_id) AS sets_attempted,
                COUNT(*)                        AS total_answered,
                SUM(is_correct)                 AS correct_answers
            ')
            ->first();

        // ── Recent attempts (last 5) ──
        $recentAttempts = QuizAttempt::where('user_id', $userId)
            ->with('quiz:id,title,type,thumbnail_url')
            ->whereIn('status', ['completed', 'submitted', 'auto_submitted'])
            ->latest('submitted_at')
            ->limit(5)
            ->get(['id', 'quiz_id', 'status', 'final_score', 'percentage', 'is_passed', 'submitted_at', 'attempt_number']);

        // ── In-progress attempts ──
        $inProgress = QuizAttempt::where('user_id', $userId)
            ->where('status', 'in_progress')
            ->with('quiz:id,title,type')
            ->latest('started_at')
            ->get(['id', 'quiz_id', 'started_at', 'time_allowed_sec']);

        // ── Subscription ──
        $subscription = $user->activeSubscription()?->load('plan:id,name,billing_cycle');

        return response()->json([
            'data' => [
                'user' => [
                    'id'         => $user->id,
                    'name'       => $user->name,
                    'email'      => $user->email,
                    'avatar_url' => $user->avatar_url,
                ],
                'quiz_stats' => [
                    'total_attempts'   => (int)   ($attemptStats->total_attempts ?? 0),
                    'passed'           => (int)   ($attemptStats->passed         ?? 0),
                    'failed'           => (int)   ($attemptStats->total_attempts ?? 0) - (int) ($attemptStats->passed ?? 0),
                    'avg_score'        => (float) ($attemptStats->avg_score      ?? 0),
                    'best_score'       => (float) ($attemptStats->best_score     ?? 0),
                    'in_progress'      => $inProgressCount,
                    'total_time_hours' => round(($attemptStats->total_time_sec ?? 0) / 3600, 1),
                ],
                'practice_stats' => [
                    'sets_attempted'   => (int) ($practiceStats->sets_attempted  ?? 0),
                    'total_answered'   => (int) ($practiceStats->total_answered  ?? 0),
                    'correct_answers'  => (int) ($practiceStats->correct_answers ?? 0),
                ],
                'recent_attempts'   => $recentAttempts,
                'in_progress'       => $inProgress,
                'available_quizzes' => Quiz::published()->public()->count(),
                'subscription'      => $subscription ? [
                    'plan'           => $subscription->plan->name ?? null,
                    'billing_cycle'  => $subscription->plan->billing_cycle ?? null,
                    'expires_at'     => $subscription->expires_at?->toDateString(),
                    'days_remaining' => $subscription->daysRemaining(),
                ] : null,
            ],
        ]);
    }
}
