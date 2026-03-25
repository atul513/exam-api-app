<?php
// ─────────────────────────────────────────────────────────────
// FILE: app/Http/Controllers/Api/V1/SubjectController.php
// ─────────────────────────────────────────────────────────────

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Models\Subject;
use App\Services\QuestionCacheService;
use Illuminate\Http\Request;
use Illuminate\Http\JsonResponse;

class SubjectController extends Controller
{
    /**
     * GET /api/v1/subjects
     * List all subjects with question counts.
     */
    public function index(Request $request): JsonResponse
    {
        $query = Subject::query()
            ->withCount('questions')
            ->withCount(['questions as approved_count' => function ($q) {
                $q->where('status', 'approved');
            }]);

        if ($request->boolean('active_only', true)) {
            $query->active();
        }

        $subjects = $query->orderBy('sort_order')->get();

        return response()->json([
            'data' => $subjects->map(fn($s) => [
                'id'              => $s->id,
                'name'            => $s->name,
                'code'            => $s->code,
                'description'     => $s->description,
                'icon_url'        => $s->icon_url,
                'sort_order'      => $s->sort_order,
                'is_active'       => $s->is_active,
                'questions_count' => $s->questions_count,
                'approved_count'  => $s->approved_count,
            ]),
        ]);
    }

    /**
     * POST /api/v1/subjects
     */
    public function store(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'name'        => 'required|string|max:255',
            'code'        => 'required|string|max:50|unique:subjects,code|alpha_dash',
            'description' => 'nullable|string|max:1000',
            'icon_url'    => 'nullable|url|max:500',
            'sort_order'  => 'nullable|integer|min:0',
            'is_active'   => 'nullable|boolean',
        ]);

        $subject = Subject::create($validated);

        QuestionCacheService::flushSubjects();

        return response()->json([
            'message' => 'Subject created successfully.',
            'data'    => $subject,
        ], 201);
    }

    /**
     * GET /api/v1/subjects/{subject}
     */
    public function show(Subject $subject): JsonResponse
    {
        $subject->loadCount('questions');
        $subject->load(['rootTopics' => function ($q) {
            $q->withCount('questions')->with(['allChildren' => function ($q2) {
                $q2->withCount('questions');
            }]);
        }]);

        return response()->json(['data' => $subject]);
    }

    /**
     * PUT /api/v1/subjects/{subject}
     */
    public function update(Request $request, Subject $subject): JsonResponse
    {
        $validated = $request->validate([
            'name'        => 'sometimes|string|max:255',
            'code'        => 'sometimes|string|max:50|alpha_dash|unique:subjects,code,' . $subject->id,
            'description' => 'nullable|string|max:1000',
            'icon_url'    => 'nullable|url|max:500',
            'sort_order'  => 'nullable|integer|min:0',
            'is_active'   => 'nullable|boolean',
        ]);

        $subject->update($validated);

        QuestionCacheService::flushSubjects();

        return response()->json([
            'message' => 'Subject updated.',
            'data'    => $subject->fresh(),
        ]);
    }

    /**
     * DELETE /api/v1/subjects/{subject}
     */
    public function destroy(Subject $subject): JsonResponse
    {
        // Prevent deleting if it has approved questions
        $approvedCount = $subject->questions()->where('status', 'approved')->count();

        if ($approvedCount > 0) {
            return response()->json([
                'message' => "Cannot delete subject with {$approvedCount} approved questions. Archive them first.",
            ], 422);
        }

        $subject->delete(); // soft delete

        QuestionCacheService::flushSubjects();

        return response()->json(['message' => 'Subject deleted.']);
    }
}
