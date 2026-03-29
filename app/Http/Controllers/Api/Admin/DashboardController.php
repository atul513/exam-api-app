<?php

namespace App\Http\Controllers\Api\Admin;

use App\Http\Controllers\Controller;
use App\Models\{User, Quiz, Question, PracticeSet, QuizAttempt, ImportBatch, UserSubscription};
use Illuminate\Http\Request;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\DB;

class DashboardController extends Controller
{
    public function index(Request $request): JsonResponse
    {
        $now       = now();
        $thisMonth = $now->copy()->startOfMonth();

        // ── User stats ──
        $userCounts = User::selectRaw('
            COUNT(*) AS total,
            SUM(CASE WHEN role = "student"  THEN 1 ELSE 0 END) AS students,
            SUM(CASE WHEN role = "teacher"  THEN 1 ELSE 0 END) AS teachers,
            SUM(CASE WHEN role = "parent"   THEN 1 ELSE 0 END) AS parents,
            SUM(CASE WHEN created_at >= ?   THEN 1 ELSE 0 END) AS new_this_month
        ', [$thisMonth])
        ->first();

        // ── Content stats ──
        $quizStats = Quiz::selectRaw('
            COUNT(*) AS total,
            SUM(CASE WHEN status = "published" THEN 1 ELSE 0 END) AS published,
            SUM(CASE WHEN status = "draft"     THEN 1 ELSE 0 END) AS draft
        ')->first();

        $questionTotal     = Question::count();
        $practiceSetTotal  = PracticeSet::count();

        // ── Attempt stats ──
        $attemptStats = QuizAttempt::selectRaw('
            COUNT(*) AS total,
            SUM(CASE WHEN created_at >= ? THEN 1 ELSE 0 END) AS this_month
        ', [$thisMonth])->first();

        // ── Subscription stats ──
        $subStats = UserSubscription::selectRaw('
            COUNT(*) AS total,
            SUM(CASE WHEN status = "active" AND (expires_at IS NULL OR expires_at > NOW()) THEN 1 ELSE 0 END) AS active,
            SUM(CASE WHEN created_at >= ? THEN amount_paid ELSE 0 END) AS revenue_this_month
        ', [$thisMonth])->first();

        // ── Recent registrations ──
        $recentUsers = User::whereIn('role', ['student', 'teacher', 'parent'])
            ->latest()
            ->limit(8)
            ->get(['id', 'name', 'email', 'role', 'created_at']);

        // ── Recent import batches ──
        $recentImports = ImportBatch::with('importer:id,name')
            ->latest()
            ->limit(5)
            ->get(['id', 'file_name', 'status', 'total_rows', 'success_count', 'error_count', 'created_at', 'imported_by']);

        return response()->json([
            'data' => [
                'user_stats' => [
                    'total'          => (int) ($userCounts->total          ?? 0),
                    'students'       => (int) ($userCounts->students       ?? 0),
                    'teachers'       => (int) ($userCounts->teachers       ?? 0),
                    'parents'        => (int) ($userCounts->parents        ?? 0),
                    'new_this_month' => (int) ($userCounts->new_this_month ?? 0),
                ],
                'content_stats' => [
                    'quizzes'       => (int) ($quizStats->total     ?? 0),
                    'published'     => (int) ($quizStats->published ?? 0),
                    'draft'         => (int) ($quizStats->draft     ?? 0),
                    'questions'     => $questionTotal,
                    'practice_sets' => $practiceSetTotal,
                ],
                'attempt_stats' => [
                    'total'      => (int) ($attemptStats->total       ?? 0),
                    'this_month' => (int) ($attemptStats->this_month  ?? 0),
                ],
                'subscription_stats' => [
                    'total'              => (int)   ($subStats->total               ?? 0),
                    'active'             => (int)   ($subStats->active              ?? 0),
                    'revenue_this_month' => (float) ($subStats->revenue_this_month  ?? 0),
                ],
                'recent_users'   => $recentUsers,
                'recent_imports' => $recentImports,
            ],
        ]);
    }

    public function users(Request $request): JsonResponse
    {
        $perPage = (int) $request->input('per_page', 15);
        $search  = $request->input('search');
        $role    = $request->input('role');

        $query = User::whereIn('role', ['teacher', 'student', 'parent'])
            ->when($search, fn($q) => $q->where(function ($q) use ($search) {
                $q->where('name', 'like', "%{$search}%")
                  ->orWhere('email', 'like', "%{$search}%");
            }))
            ->when($role, fn($q) => $q->where('role', $role));

        return response()->json($query->latest()->paginate($perPage));
    }

    public function createUser(Request $request): JsonResponse
    {
        $data = $request->validate([
            'name'     => 'required|string|max:255',
            'email'    => 'required|email|unique:users',
            'password' => 'required|string|min:6|confirmed',
            'role'     => 'required|in:teacher,student,parent',
        ]);

        $user = User::create($data);

        return response()->json(['message' => 'User created.', 'data' => $user], 201);
    }
}
