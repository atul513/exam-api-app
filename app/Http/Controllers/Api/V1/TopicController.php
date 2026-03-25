<?php
// ─────────────────────────────────────────────────────────────
// FILE: app/Http/Controllers/Api/V1/TopicController.php
// ─────────────────────────────────────────────────────────────

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Models\{Subject, Topic};
use App\Services\QuestionCacheService;
use Illuminate\Http\Request;
use Illuminate\Http\JsonResponse;

class TopicController extends Controller
{
    /**
     * GET /api/v1/subjects/{subject}/topics
     * Returns flat list with depth (for dropdowns) or tree (for UI).
     */
    public function index(Request $request, Subject $subject): JsonResponse
    {
        $query = Topic::where('subject_id', $subject->id)
            ->withCount('questions');

        if ($request->boolean('active_only', true)) {
            $query->active();
        }

        // Two modes: flat (for select dropdowns) or tree (for tree UI)
        if ($request->query('format') === 'tree') {
            $topics = $query->whereNull('parent_topic_id')
                ->with(['allChildren' => function ($q) {
                    $q->withCount('questions')->orderBy('sort_order');
                }])
                ->orderBy('sort_order')
                ->get();

            return response()->json(['data' => $topics, 'format' => 'tree']);
        }

        // Default: flat list ordered by depth + sort_order
        $topics = $query->orderBy('depth')
            ->orderBy('sort_order')
            ->get()
            ->map(fn($t) => [
                'id'              => $t->id,
                'name'            => $t->name,
                'code'            => $t->code,
                'depth'           => $t->depth,
                'parent_topic_id' => $t->parent_topic_id,
                'sort_order'      => $t->sort_order,
                'is_active'       => $t->is_active,
                'questions_count' => $t->questions_count,
                // Indented name for dropdowns
                'display_name'    => str_repeat('— ', $t->depth) . $t->name,
            ]);

        return response()->json(['data' => $topics, 'format' => 'flat']);
    }

    /**
     * POST /api/v1/topics
     */
    public function store(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'subject_id'      => 'required|exists:subjects,id',
            'name'            => 'required|string|max:255',
            'code'            => 'required|string|max:100|unique:topics,code|alpha_dash',
            'parent_topic_id' => 'nullable|exists:topics,id',
            'sort_order'      => 'nullable|integer|min:0',
            'is_active'       => 'nullable|boolean',
        ]);

        $subject = Subject::findOrFail($validated['subject_id']);

        // Calculate depth from parent
        $depth = 0;
        if (!empty($validated['parent_topic_id'])) {
            $parent = Topic::find($validated['parent_topic_id']);

            // Ensure parent belongs to same subject
            if ($parent && $parent->subject_id !== $subject->id) {
                return response()->json([
                    'message' => 'Parent topic does not belong to this subject.',
                ], 422);
            }

            $depth = ($parent->depth ?? 0) + 1;

            // Max 3 levels deep
            if ($depth > 2) {
                return response()->json([
                    'message' => 'Maximum nesting depth is 3 levels (topic → subtopic → sub-subtopic).',
                ], 422);
            }
        }

        $topic = Topic::create(array_merge($validated, [
            'depth' => $depth,
        ]));

        QuestionCacheService::flushTopics($subject->id);

        return response()->json([
            'message' => 'Topic created.',
            'data'    => $topic,
        ], 201);
    }

    /**
     * GET /api/v1/topics/{topic}
     */
    public function show(Topic $topic): JsonResponse
    {
        $topic->load([
            'subject:id,name,code',
            'parent:id,name,code',
            'children' => fn($q) => $q->withCount('questions'),
        ])->loadCount('questions');

        return response()->json(['data' => $topic]);
    }

    /**
     * PUT /api/v1/topics/{topic}
     */
    public function update(Request $request, Topic $topic): JsonResponse
    {
        $validated = $request->validate([
            'name'            => 'sometimes|string|max:255',
            'code'            => 'sometimes|string|max:100|alpha_dash|unique:topics,code,' . $topic->id,
            'parent_topic_id' => 'nullable|exists:topics,id',
            'sort_order'      => 'nullable|integer|min:0',
            'is_active'       => 'nullable|boolean',
        ]);

        // Prevent circular reference: can't set self as parent
        if (isset($validated['parent_topic_id']) && $validated['parent_topic_id'] == $topic->id) {
            return response()->json([
                'message' => 'A topic cannot be its own parent.',
            ], 422);
        }

        $topic->update($validated);

        QuestionCacheService::flushTopics($topic->subject_id);

        return response()->json([
            'message' => 'Topic updated.',
            'data'    => $topic->fresh(),
        ]);
    }

    /**
     * DELETE /api/v1/topics/{topic}
     */
    public function destroy(Topic $topic): JsonResponse
    {
        $questionCount = $topic->questions()->count();
        $childCount = $topic->children()->count();

        if ($questionCount > 0) {
            return response()->json([
                'message' => "Cannot delete topic with {$questionCount} questions. Move or archive them first.",
            ], 422);
        }

        if ($childCount > 0) {
            return response()->json([
                'message' => "Cannot delete topic with {$childCount} sub-topics. Delete sub-topics first.",
            ], 422);
        }

        $subjectId = $topic->subject_id;
        $topic->delete();

        QuestionCacheService::flushTopics($subjectId);

        return response()->json(['message' => 'Topic deleted.']);
    }
}

