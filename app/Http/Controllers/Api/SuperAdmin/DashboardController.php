<?php

namespace App\Http\Controllers\Api\SuperAdmin;

use App\Http\Controllers\Controller;
use App\Models\{User, Quiz, Question, PracticeSet, QuizAttempt, UserSubscription, Plan, ImportBatch};
use Illuminate\Http\Request;
use Illuminate\Http\JsonResponse;

class DashboardController extends Controller
{
    public function index(Request $request): JsonResponse
    {
        $now        = now();
        $thisMonth  = $now->copy()->startOfMonth();
        $lastMonth  = $now->copy()->subMonth()->startOfMonth();
        $lastMonthEnd = $now->copy()->subMonth()->endOfMonth();

        // ── User breakdown ──
        $userStats = User::selectRaw('
            COUNT(*) AS total,
            SUM(CASE WHEN role = "superadmin" THEN 1 ELSE 0 END) AS superadmins,
            SUM(CASE WHEN role = "admin"      THEN 1 ELSE 0 END) AS admins,
            SUM(CASE WHEN role = "teacher"    THEN 1 ELSE 0 END) AS teachers,
            SUM(CASE WHEN role = "student"    THEN 1 ELSE 0 END) AS students,
            SUM(CASE WHEN role = "parent"     THEN 1 ELSE 0 END) AS parents,
            SUM(CASE WHEN created_at >= ?     THEN 1 ELSE 0 END) AS new_this_month,
            SUM(CASE WHEN created_at BETWEEN ? AND ? THEN 1 ELSE 0 END) AS new_last_month
        ', [$thisMonth, $lastMonth, $lastMonthEnd])
        ->first();

        // ── Content ──
        $contentStats = [
            'quizzes'       => Quiz::count(),
            'published'     => Quiz::where('status', 'published')->count(),
            'questions'     => Question::count(),
            'practice_sets' => PracticeSet::count(),
        ];

        // ── Attempts ──
        $attemptStats = QuizAttempt::selectRaw('
            COUNT(*) AS total,
            SUM(CASE WHEN created_at >= ?          THEN 1 ELSE 0 END) AS this_month,
            SUM(CASE WHEN created_at BETWEEN ? AND ? THEN 1 ELSE 0 END) AS last_month
        ', [$thisMonth, $lastMonth, $lastMonthEnd])->first();

        // ── Revenue ──
        $revenueStats = UserSubscription::selectRaw('
            SUM(amount_paid) AS total_revenue,
            SUM(CASE WHEN created_at >= ?             THEN amount_paid ELSE 0 END) AS this_month,
            SUM(CASE WHEN created_at BETWEEN ? AND ?  THEN amount_paid ELSE 0 END) AS last_month,
            SUM(CASE WHEN status = "active" AND (expires_at IS NULL OR expires_at > NOW()) THEN 1 ELSE 0 END) AS active_subs
        ', [$thisMonth, $lastMonth, $lastMonthEnd])->first();

        // ── Plans overview ──
        $plans = Plan::withCount(['activeSubscriptions'])
            ->where('is_active', true)
            ->orderBy('sort_order')
            ->get(['id', 'name', 'price', 'billing_cycle']);

        // ── Recent imports ──
        $recentImports = ImportBatch::with('importer:id,name')
            ->latest()->limit(5)
            ->get(['id', 'file_name', 'status', 'total_rows', 'success_count', 'error_count', 'created_at', 'imported_by']);

        // ── Recent registrations ──
        $recentUsers = User::latest()->limit(10)
            ->get(['id', 'name', 'email', 'role', 'created_at']);

        return response()->json([
            'data' => [
                'user_stats' => [
                    'total'          => (int) ($userStats->total          ?? 0),
                    'superadmins'    => (int) ($userStats->superadmins    ?? 0),
                    'admins'         => (int) ($userStats->admins         ?? 0),
                    'teachers'       => (int) ($userStats->teachers       ?? 0),
                    'students'       => (int) ($userStats->students       ?? 0),
                    'parents'        => (int) ($userStats->parents        ?? 0),
                    'new_this_month' => (int) ($userStats->new_this_month ?? 0),
                    'new_last_month' => (int) ($userStats->new_last_month ?? 0),
                ],
                'content_stats' => $contentStats,
                'attempt_stats' => [
                    'total'      => (int) ($attemptStats->total      ?? 0),
                    'this_month' => (int) ($attemptStats->this_month ?? 0),
                    'last_month' => (int) ($attemptStats->last_month ?? 0),
                ],
                'revenue_stats' => [
                    'total_revenue' => (float) ($revenueStats->total_revenue ?? 0),
                    'this_month'    => (float) ($revenueStats->this_month    ?? 0),
                    'last_month'    => (float) ($revenueStats->last_month    ?? 0),
                    'active_subs'   => (int)   ($revenueStats->active_subs  ?? 0),
                ],
                'plans'          => $plans,
                'recent_imports' => $recentImports,
                'recent_users'   => $recentUsers,
            ],
        ]);
    }

    public function users(Request $request): JsonResponse
    {
        $perPage = (int) $request->input('per_page', 15);
        $search  = $request->input('search');
        $role    = $request->input('role');

        $query = User::query()
            ->when($search, fn($q) => $q->where(function ($q) use ($search) {
                $q->where('name', 'like', "%{$search}%")
                  ->orWhere('email', 'like', "%{$search}%");
            }))
            ->when($role, fn($q) => $q->where('role', $role));

        return response()->json($query->latest()->paginate($perPage));
    }

    public function updateUserRole(Request $request, User $user): JsonResponse
    {
        $request->validate([
            'role' => 'required|in:' . implode(',', User::ROLES),
        ]);

        $user->update(['role' => $request->role]);

        return response()->json([
            'message' => 'Role updated.',
            'data'    => $user->fresh(['id', 'name', 'email', 'role']),
        ]);
    }
}
