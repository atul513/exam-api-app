<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Models\{ExamSection, ExamSectionLink};
use App\Services\ExamSectionService;
use Illuminate\Http\Request;
use Illuminate\Http\JsonResponse;

class ExamSectionController extends Controller
{
    public function __construct(private ExamSectionService $service) {}

    /**
     * GET /api/v1/exam-sections/types
     */
    public function types(): JsonResponse
    {
        return response()->json(['data' => ExamSectionService::availableTypes()]);
    }

    /**
     * GET /api/v1/exam-sections
     * Supports: ?type=exam&parent_id=1&roots=1&search=jee&format=tree&is_active=1
     */
    public function index(Request $request): JsonResponse
    {
        $result = $this->service->list($request->all());

        return response()->json(
            is_array($result)
                ? ['data' => $result, 'format' => 'tree']
                : $result
        );
    }

    /**
     * POST /api/v1/exam-sections
     */
    public function store(Request $request): JsonResponse
    {
        $data = $request->validate([
            'parent_id'   => 'nullable|exists:exam_sections,id',
            'name'        => 'required|string|max:255',
            'slug'        => 'nullable|string|max:255|unique:exam_sections,slug|alpha_dash',
            'code'        => 'nullable|string|max:50|unique:exam_sections,code',
            'type'        => 'required|string|max:50',
            'description' => 'nullable|string|max:2000',
            'short_name'  => 'nullable|string|max:50',
            'icon_url'    => 'nullable|url|max:500',
            'image_url'   => 'nullable|url|max:500',
            'meta'        => 'nullable|array',
            'sort_order'  => 'nullable|integer|min:0',
            'is_active'   => 'nullable|boolean',
            'is_featured' => 'nullable|boolean',
        ]);

        if (!empty($data['parent_id'])) {
            $parent = ExamSection::find($data['parent_id']);
            if ($parent && $parent->depth >= 5) {
                return response()->json(['message' => 'Maximum 6 levels of nesting allowed.'], 422);
            }
        }

        $section = $this->service->create($data);

        return response()->json([
            'message' => 'Exam section created.',
            'data'    => $section->load('parent:id,name,type'),
        ], 201);
    }

    /**
     * GET /api/v1/exam-sections/{examSection}
     */
    public function show(ExamSection $examSection): JsonResponse
    {
        $examSection->load([
            'parent:id,name,type,slug',
            'children' => fn($q) => $q->active()->orderBy('sort_order')->withCount('children'),
        ]);

        return response()->json([
            'data'       => $examSection,
            'breadcrumb' => $examSection->getBreadcrumb(),
        ]);
    }

    /**
     * PUT /api/v1/exam-sections/{examSection}
     */
    public function update(Request $request, ExamSection $examSection): JsonResponse
    {
        $data = $request->validate([
            'parent_id'   => 'nullable|exists:exam_sections,id',
            'name'        => 'sometimes|string|max:255',
            'slug'        => 'sometimes|string|max:255|alpha_dash|unique:exam_sections,slug,' . $examSection->id,
            'code'        => 'sometimes|string|max:50|unique:exam_sections,code,' . $examSection->id,
            'type'        => 'sometimes|string|max:50',
            'description' => 'nullable|string|max:2000',
            'short_name'  => 'nullable|string|max:50',
            'icon_url'    => 'nullable|url|max:500',
            'image_url'   => 'nullable|url|max:500',
            'meta'        => 'nullable|array',
            'sort_order'  => 'nullable|integer|min:0',
            'is_active'   => 'nullable|boolean',
            'is_featured' => 'nullable|boolean',
        ]);

        if (isset($data['parent_id']) && $data['parent_id'] == $examSection->id) {
            return response()->json(['message' => 'Cannot be its own parent.'], 422);
        }

        $section = $this->service->update($examSection, $data);

        return response()->json(['message' => 'Updated.', 'data' => $section]);
    }

    /**
     * DELETE /api/v1/exam-sections/{examSection}
     */
    public function destroy(ExamSection $examSection): JsonResponse
    {
        $childCount = $examSection->children()->count();
        if ($childCount > 0) {
            return response()->json([
                'message' => "Cannot delete: has {$childCount} child sections. Delete or move them first.",
            ], 422);
        }

        $examSection->delete();
        return response()->json(['message' => 'Deleted.']);
    }

    /**
     * GET /api/v1/exam-sections/{examSection}/tree
     */
    public function tree(ExamSection $examSection): JsonResponse
    {
        return response()->json(['data' => $this->service->getTree($examSection)]);
    }

    /**
     * GET /api/v1/exam-sections/{examSection}/content
     * ?status=published
     */
    public function content(Request $request, ExamSection $examSection): JsonResponse
    {
        $content = $this->service->getContent($examSection, $request->all());

        return response()->json([
            'data'       => $content,
            'section'    => $examSection->only(['id', 'name', 'type', 'slug']),
            'breadcrumb' => $examSection->getBreadcrumb(),
        ]);
    }

    /**
     * GET /api/v1/exam-sections/{examSection}/breadcrumb
     */
    public function breadcrumb(ExamSection $examSection): JsonResponse
    {
        return response()->json(['data' => $examSection->getBreadcrumb()]);
    }

    /**
     * POST /api/v1/exam-sections/{examSection}/link
     * Body: { "linkable_type": "quiz|practice_set", "linkable_id": 5 }
     */
    public function link(Request $request, ExamSection $examSection): JsonResponse
    {
        $data = $request->validate([
            'linkable_type' => 'required|in:quiz,practice_set',
            'linkable_id'   => 'required|integer',
        ]);

        $modelMap = [
            'quiz'         => \App\Models\Quiz::class,
            'practice_set' => \App\Models\PracticeSet::class,
        ];

        $type  = $modelMap[$data['linkable_type']];
        if (!$type::find($data['linkable_id'])) {
            return response()->json(['message' => 'Entity not found.'], 404);
        }

        ExamSectionLink::firstOrCreate([
            'exam_section_id' => $examSection->id,
            'linkable_type'   => $type,
            'linkable_id'     => $data['linkable_id'],
        ]);

        return response()->json(['message' => 'Linked successfully.']);
    }

    /**
     * DELETE /api/v1/exam-sections/{examSection}/unlink
     */
    public function unlink(Request $request, ExamSection $examSection): JsonResponse
    {
        $data = $request->validate([
            'linkable_type' => 'required|in:quiz,practice_set',
            'linkable_id'   => 'required|integer',
        ]);

        $modelMap = [
            'quiz'         => \App\Models\Quiz::class,
            'practice_set' => \App\Models\PracticeSet::class,
        ];

        ExamSectionLink::where('exam_section_id', $examSection->id)
            ->where('linkable_type', $modelMap[$data['linkable_type']])
            ->where('linkable_id', $data['linkable_id'])
            ->delete();

        return response()->json(['message' => 'Unlinked.']);
    }

    /**
     * POST /api/v1/exam-sections/bulk-create
     */
    public function bulkCreate(Request $request): JsonResponse
    {
        $data = $request->validate([
            'sections'              => 'required|array|min:1|max:100',
            'sections.*.name'       => 'required|string|max:255',
            'sections.*.type'       => 'required|string|max:50',
            'sections.*.parent_id'  => 'nullable|integer',
            'sections.*.code'       => 'nullable|string|max:50',
            'sections.*.meta'       => 'nullable|array',
            'sections.*.sort_order' => 'nullable|integer',
        ]);

        $created = [];
        $idMap   = [];

        foreach ($data['sections'] as $i => $s) {
            $parentId = $s['parent_id'] ?? null;
            if ($parentId && isset($idMap[$parentId])) {
                $parentId = $idMap[$parentId];
            }

            $section = ExamSection::create([
                'name'       => $s['name'],
                'type'       => $s['type'],
                'code'       => $s['code'] ?? null,
                'parent_id'  => $parentId,
                'meta'       => $s['meta'] ?? null,
                'sort_order' => $s['sort_order'] ?? $i,
            ]);

            $idMap[$i + 1] = $section->id;
            $created[]     = $section;
        }

        return response()->json([
            'message' => count($created) . ' sections created.',
            'data'    => $created,
            'id_map'  => $idMap,
        ], 201);
    }
}
