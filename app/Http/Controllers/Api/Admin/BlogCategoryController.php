<?php

namespace App\Http\Controllers\Api\Admin;

use App\Http\Controllers\Controller;
use App\Models\BlogCategory;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class BlogCategoryController extends Controller
{
    public function index(Request $request): JsonResponse
    {
        $query = BlogCategory::withCount('blogs')
            ->orderBy('sort_order')
            ->orderBy('name');

        if ($request->filled('status')) {
            $query->where('status', $request->status);
        }

        if ($request->boolean('with_children')) {
            $query->parents()->with(['children' => fn ($q) => $q->withCount('blogs')->orderBy('sort_order')]);
        }

        return response()->json([
            'success' => true,
            'data'    => $query->get(),
        ]);
    }

    public function show(int $id): JsonResponse
    {
        $category = BlogCategory::withCount('blogs')
            ->with(['parent', 'children' => fn ($q) => $q->withCount('blogs')])
            ->findOrFail($id);

        return response()->json([
            'success' => true,
            'data'    => $category,
        ]);
    }

    public function store(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'name'             => 'required|string|max:100',
            'slug'             => 'nullable|string|unique:blog_categories,slug|max:120',
            'description'      => 'nullable|string|max:500',
            'meta_title'       => 'nullable|string|max:60',
            'meta_description' => 'nullable|string|max:160',
            'og_image'         => 'nullable|string|max:500',
            'status'           => 'nullable|in:active,inactive',
            'parent_id'        => 'nullable|exists:blog_categories,id',
            'sort_order'       => 'nullable|integer|min:0',
        ]);

        $category = BlogCategory::create($validated);

        return response()->json([
            'success' => true,
            'message' => 'Category created successfully.',
            'data'    => $category,
        ], 201);
    }

    public function update(Request $request, int $id): JsonResponse
    {
        $category  = BlogCategory::findOrFail($id);
        $validated = $request->validate([
            'name'             => 'sometimes|required|string|max:100',
            'slug'             => "nullable|string|unique:blog_categories,slug,{$id}|max:120",
            'description'      => 'nullable|string|max:500',
            'meta_title'       => 'nullable|string|max:60',
            'meta_description' => 'nullable|string|max:160',
            'og_image'         => 'nullable|string|max:500',
            'status'           => 'nullable|in:active,inactive',
            'parent_id'        => "nullable|exists:blog_categories,id|not_in:{$id}",
            'sort_order'       => 'nullable|integer|min:0',
        ]);

        $category->update($validated);

        return response()->json([
            'success' => true,
            'message' => 'Category updated successfully.',
            'data'    => $category,
        ]);
    }

    public function destroy(int $id): JsonResponse
    {
        $category = BlogCategory::withCount('blogs')->findOrFail($id);

        if ($category->blogs_count > 0) {
            return response()->json([
                'success' => false,
                'message' => "Cannot delete: {$category->blogs_count} blog(s) are assigned to this category. Reassign them first.",
            ], 422);
        }

        $category->delete();

        return response()->json([
            'success' => true,
            'message' => 'Category deleted successfully.',
        ]);
    }
}