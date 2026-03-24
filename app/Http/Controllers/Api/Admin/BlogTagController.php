<?php

namespace App\Http\Controllers\Api\Admin;

use App\Http\Controllers\Controller;
use App\Models\BlogTag;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class BlogTagController extends Controller
{
    public function index(Request $request): JsonResponse
    {
        $query = BlogTag::withCount('blogs')->orderBy('name');

        if ($request->filled('search')) {
            $query->where('name', 'like', "%{$request->search}%");
        }

        return response()->json([
            'success' => true,
            'data'    => $query->get(),
        ]);
    }

    public function show(int $id): JsonResponse
    {
        $tag = BlogTag::withCount('blogs')->findOrFail($id);

        return response()->json([
            'success' => true,
            'data'    => $tag,
        ]);
    }

    public function store(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'name'             => 'required|string|max:50|unique:blog_tags,name',
            'slug'             => 'nullable|string|unique:blog_tags,slug|max:60',
            'meta_title'       => 'nullable|string|max:60',
            'meta_description' => 'nullable|string|max:160',
        ]);

        $tag = BlogTag::create($validated);

        return response()->json([
            'success' => true,
            'message' => 'Tag created successfully.',
            'data'    => $tag,
        ], 201);
    }

    public function update(Request $request, int $id): JsonResponse
    {
        $tag       = BlogTag::findOrFail($id);
        $validated = $request->validate([
            'name'             => "sometimes|required|string|max:50|unique:blog_tags,name,{$id}",
            'slug'             => "nullable|string|unique:blog_tags,slug,{$id}|max:60",
            'meta_title'       => 'nullable|string|max:60',
            'meta_description' => 'nullable|string|max:160',
        ]);

        $tag->update($validated);

        return response()->json([
            'success' => true,
            'message' => 'Tag updated successfully.',
            'data'    => $tag,
        ]);
    }

    public function destroy(int $id): JsonResponse
    {
        $tag = BlogTag::findOrFail($id);
        $tag->blogs()->detach();
        $tag->delete();

        return response()->json([
            'success' => true,
            'message' => 'Tag deleted successfully.',
        ]);
    }
}