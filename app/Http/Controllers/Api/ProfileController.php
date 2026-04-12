<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\{Quiz, Question, PracticeSet, QuizAttempt, PracticeSetProgress, User};
use Illuminate\Http\Request;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\Storage;
use Illuminate\Validation\Rule;

class ProfileController extends Controller
{
    /**
     * GET /api/profile
     * Return current user's full profile with role-specific stats.
     * Works for ALL roles: student, teacher, admin, superadmin, parent.
     */
    public function show(Request $request): JsonResponse
    {
        $user = $request->user();

        return response()->json([
            'data' => array_merge(
                $this->formatUser($user),
                ['stats' => $this->getStats($user)]
            ),
        ]);
    }

    /**
     * PUT /api/profile
     * Update personal info + address.
     */
    public function update(Request $request): JsonResponse
    {
        $user = $request->user();

        $data = $request->validate([
            'first_name'  => 'nullable|string|max:100',
            'last_name'   => 'nullable|string|max:100',
            'username'    => ['nullable', 'string', 'max:50', 'alpha_dash', Rule::unique('users', 'username')->ignore($user->id)],
            'email'       => ['nullable', 'email', Rule::unique('users', 'email')->ignore($user->id)],
            'phone_code'  => 'nullable|string|max:10',
            'phone'       => 'nullable|string|max:20',
            'country'     => 'nullable|string|max:100',
            'address'     => 'nullable|string|max:255',
            'city'        => 'nullable|string|max:100',
            'postal_code' => 'nullable|string|max:20',
        ]);

        if (isset($data['first_name']) || isset($data['last_name'])) {
            $first = $data['first_name'] ?? $user->first_name;
            $last  = $data['last_name']  ?? $user->last_name;
            $data['name'] = trim("{$first} {$last}") ?: $user->name;
        }

        $user->update($data);

        return response()->json([
            'message' => 'Profile updated.',
            'data'    => array_merge(
                $this->formatUser($user->fresh()),
                ['stats' => $this->getStats($user->fresh())]
            ),
        ]);
    }

    /**
     * POST /api/profile/avatar
     */
    public function uploadAvatar(Request $request): JsonResponse
    {
        $request->validate([
            'avatar' => 'required|image|mimes:jpg,jpeg,png,webp|max:2048',
        ]);

        $user = $request->user();

        if ($user->avatar) {
            Storage::disk('public')->delete($user->avatar);
        }

        $path = $request->file('avatar')->store('avatars', 'public');
        $user->update(['avatar' => $path]);

        return response()->json([
            'message'    => 'Avatar uploaded.',
            'avatar_url' => asset('storage/' . $path),
        ]);
    }

    /**
     * DELETE /api/profile/avatar
     */
    public function removeAvatar(Request $request): JsonResponse
    {
        $user = $request->user();

        if ($user->avatar) {
            Storage::disk('public')->delete($user->avatar);
            $user->update(['avatar' => null]);
        }

        return response()->json(['message' => 'Avatar removed.']);
    }

    // ── Helpers ─────────────────────────────────────────────────────────

    private function formatUser($user): array
    {
        $sub = $user->activeSubscription()?->load('plan:id,name,billing_cycle,price');

        return [
            'id'          => $user->id,
            'name'        => $user->name,
            'first_name'  => $user->first_name,
            'last_name'   => $user->last_name,
            'username'    => $user->username,
            'email'       => $user->email,
            'phone_code'  => $user->phone_code,
            'phone'       => $user->phone,
            'avatar_url'  => $user->avatar_url,
            'country'     => $user->country,
            'address'     => $user->address,
            'city'        => $user->city,
            'postal_code' => $user->postal_code,
            'role'        => $user->role,
            'member_since'=> $user->created_at?->toDateString(),
            'subscription' => $sub ? [
                'plan_name'      => $sub->plan->name ?? null,
                'billing_cycle'  => $sub->plan->billing_cycle ?? null,
                'expires_at'     => $sub->expires_at?->toDateString(),
                'days_remaining' => $sub->daysRemaining(),
                'is_active'      => $sub->isActive(),
            ] : null,
        ];
    }

    /**
     * Returns role-specific stats for the profile page.
     */
    private function getStats(User $user): array
    {
        return match ($user->role) {
            'student'                  => $this->studentStats($user),
            'teacher'                  => $this->teacherStats($user),
            'admin', 'superadmin'      => $this->adminStats($user),
            'parent'                   => $this->parentStats($user),
            default                    => [],
        };
    }

    private function studentStats(User $user): array
    {
        $userId = $user->id;

        $attemptStats = QuizAttempt::where('user_id', $userId)
            ->whereIn('status', ['completed', 'submitted', 'auto_submitted'])
            ->selectRaw('
                COUNT(*)                  AS total_attempts,
                SUM(is_passed)            AS passed,
                ROUND(AVG(percentage),1)  AS avg_score,
                ROUND(MAX(percentage),1)  AS best_score
            ')
            ->first();

        $practiceStats = PracticeSetProgress::where('user_id', $userId)
            ->selectRaw('
                COUNT(DISTINCT practice_set_id) AS sets_attempted,
                COUNT(*)                        AS total_answered,
                SUM(is_correct)                 AS correct_answers
            ')
            ->first();

        $recentAttempts = QuizAttempt::where('user_id', $userId)
            ->with('quiz:id,title,type')
            ->whereIn('status', ['completed', 'submitted', 'auto_submitted'])
            ->latest('submitted_at')
            ->limit(5)
            ->get(['id', 'quiz_id', 'status', 'final_score', 'percentage', 'is_passed', 'submitted_at']);

        return [
            'quiz' => [
                'total_attempts' => (int)   ($attemptStats->total_attempts ?? 0),
                'passed'         => (int)   ($attemptStats->passed         ?? 0),
                'failed'         => (int)   ($attemptStats->total_attempts ?? 0) - (int) ($attemptStats->passed ?? 0),
                'avg_score'      => (float) ($attemptStats->avg_score      ?? 0),
                'best_score'     => (float) ($attemptStats->best_score     ?? 0),
            ],
            'practice' => [
                'sets_attempted'  => (int) ($practiceStats->sets_attempted  ?? 0),
                'total_answered'  => (int) ($practiceStats->total_answered  ?? 0),
                'correct_answers' => (int) ($practiceStats->correct_answers ?? 0),
            ],
            'recent_attempts' => $recentAttempts,
        ];
    }

    private function teacherStats(User $user): array
    {
        $userId = $user->id;

        $quizStats = Quiz::where('created_by', $userId)
            ->selectRaw('
                COUNT(*) AS total,
                SUM(CASE WHEN status = "published" THEN 1 ELSE 0 END) AS published,
                SUM(CASE WHEN status = "draft"     THEN 1 ELSE 0 END) AS draft
            ')
            ->first();

        $questionStats = Question::where('created_by', $userId)
            ->selectRaw('COUNT(*) AS total, SUM(CASE WHEN status = "active" THEN 1 ELSE 0 END) AS active')
            ->first();

        $practiceStats = PracticeSet::where('created_by', $userId)
            ->selectRaw('COUNT(*) AS total, SUM(CASE WHEN status = "published" THEN 1 ELSE 0 END) AS published')
            ->first();

        $myQuizIds = Quiz::where('created_by', $userId)->pluck('id');
        $attemptStats = QuizAttempt::whereIn('quiz_id', $myQuizIds)
            ->selectRaw('
                COUNT(*)                 AS total_attempts,
                COUNT(DISTINCT user_id)  AS unique_students,
                SUM(is_passed)           AS total_passed,
                ROUND(AVG(percentage),1) AS avg_score
            ')
            ->first();

        return [
            'quizzes' => [
                'total'     => (int) ($quizStats->total     ?? 0),
                'published' => (int) ($quizStats->published ?? 0),
                'draft'     => (int) ($quizStats->draft     ?? 0),
            ],
            'questions' => [
                'total'  => (int) ($questionStats->total  ?? 0),
                'active' => (int) ($questionStats->active ?? 0),
            ],
            'practice_sets' => [
                'total'     => (int) ($practiceStats->total     ?? 0),
                'published' => (int) ($practiceStats->published ?? 0),
            ],
            'student_attempts' => [
                'total'           => (int)   ($attemptStats->total_attempts  ?? 0),
                'unique_students' => (int)   ($attemptStats->unique_students ?? 0),
                'passed'          => (int)   ($attemptStats->total_passed    ?? 0),
                'avg_score'       => (float) ($attemptStats->avg_score       ?? 0),
            ],
        ];
    }

    private function adminStats(User $user): array
    {
        return [
            'users' => [
                'total'    => User::count(),
                'students' => User::where('role', 'student')->count(),
                'teachers' => User::where('role', 'teacher')->count(),
                'parents'  => User::where('role', 'parent')->count(),
            ],
            'content' => [
                'quizzes'       => Quiz::count(),
                'questions'     => Question::count(),
                'practice_sets' => PracticeSet::count(),
            ],
            'attempts' => QuizAttempt::count(),
        ];
    }

    private function parentStats(User $user): array
    {
        $children = $user->children()->with([])->get(['id', 'name', 'email', 'avatar', 'created_at']);

        $childIds = $children->pluck('id');

        $childAttempts = QuizAttempt::whereIn('user_id', $childIds)
            ->whereIn('status', ['completed', 'submitted', 'auto_submitted'])
            ->selectRaw('
                user_id,
                COUNT(*)                 AS total_attempts,
                ROUND(AVG(percentage),1) AS avg_score,
                SUM(is_passed)           AS passed
            ')
            ->groupBy('user_id')
            ->get()
            ->keyBy('user_id');

        $childrenData = $children->map(function ($child) use ($childAttempts) {
            $stats = $childAttempts->get($child->id);
            return [
                'id'             => $child->id,
                'name'           => $child->name,
                'email'          => $child->email,
                'avatar_url'     => $child->avatar_url,
                'total_attempts' => (int)   ($stats->total_attempts ?? 0),
                'avg_score'      => (float) ($stats->avg_score      ?? 0),
                'passed'         => (int)   ($stats->passed         ?? 0),
            ];
        });

        return [
            'children_count' => $children->count(),
            'children'       => $childrenData,
        ];
    }
}
