<?php

// FILE: app/Http/Controllers/Api/V1/QuizCategoryController.php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Models\QuizCategory;
use Illuminate\Http\Request;
use Illuminate\Http\JsonResponse;

class QuizCategoryController extends Controller
{
    /**
     * GET /api/v1/quiz-categories
     */
    public function index(Request $request): JsonResponse
    {
        $query = QuizCategory::query()
            ->withCount('quizzes');

        if ($request->boolean('active_only', true)) {
            $query->active();
        }

        if ($request->filled('search')) {
            $query->where('name', 'like', '%' . $request->search . '%');
        }

        // Tree format: only root categories with nested children
        if ($request->query('format') === 'tree') {
            $categories = $query->whereNull('parent_id')
                ->with(['children' => function ($q) {
                    $q->withCount('quizzes')->orderBy('sort_order');
                }])
                ->orderBy('sort_order')
                ->get();

            return response()->json(['data' => $categories, 'format' => 'tree']);
        }

        // Default: flat list
        $categories = $query->orderBy('sort_order')->get();

        return response()->json(['data' => $categories]);
    }

    /**
     * POST /api/v1/quiz-categories
     */
    public function store(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'name'        => 'required|string|max:255',
            'slug'        => 'nullable|string|max:255|unique:quiz_categories,slug|alpha_dash',
            'parent_id'   => 'nullable|exists:quiz_categories,id',
            'description' => 'nullable|string|max:1000',
            'icon_url'    => 'nullable|url|max:500',
            'sort_order'  => 'nullable|integer|min:0',
            'is_active'   => 'nullable|boolean',
        ]);

        $category = QuizCategory::create($validated);

        return response()->json([
            'message' => 'Category created.',
            'data'    => $category,
        ], 201);
    }

    /**
     * GET /api/v1/quiz-categories/{quizCategory}
     */
    public function show(QuizCategory $quizCategory): JsonResponse
    {
        $quizCategory->load([
            'parent:id,name,slug',
            'children' => fn($q) => $q->withCount('quizzes')->orderBy('sort_order'),
        ])->loadCount('quizzes');

        return response()->json(['data' => $quizCategory]);
    }

    /**
     * PUT /api/v1/quiz-categories/{quizCategory}
     */
    public function update(Request $request, QuizCategory $quizCategory): JsonResponse
    {
        $validated = $request->validate([
            'name'        => 'sometimes|string|max:255',
            'slug'        => 'sometimes|string|max:255|alpha_dash|unique:quiz_categories,slug,' . $quizCategory->id,
            'parent_id'   => 'nullable|exists:quiz_categories,id',
            'description' => 'nullable|string|max:1000',
            'icon_url'    => 'nullable|url|max:500',
            'sort_order'  => 'nullable|integer|min:0',
            'is_active'   => 'nullable|boolean',
        ]);

        if (isset($validated['parent_id']) && $validated['parent_id'] == $quizCategory->id) {
            return response()->json(['message' => 'A category cannot be its own parent.'], 422);
        }

        $quizCategory->update($validated);

        return response()->json([
            'message' => 'Category updated.',
            'data'    => $quizCategory->fresh(),
        ]);
    }

    /**
     * DELETE /api/v1/quiz-categories/{quizCategory}
     */
    public function destroy(QuizCategory $quizCategory): JsonResponse
    {
        $quizCount = $quizCategory->quizzes()->count();
        $childCount = $quizCategory->children()->count();

        if ($quizCount > 0) {
            return response()->json([
                'message' => "Cannot delete category with {$quizCount} quizzes. Move them first.",
            ], 422);
        }

        if ($childCount > 0) {
            return response()->json([
                'message' => "Cannot delete category with {$childCount} sub-categories. Delete them first.",
            ], 422);
        }

        $quizCategory->delete();

        return response()->json(['message' => 'Category deleted.']);
    }
}