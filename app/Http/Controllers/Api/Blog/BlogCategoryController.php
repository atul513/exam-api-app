<?php

namespace App\Http\Controllers\Api\Blog;

use App\Http\Controllers\Controller;
use App\Models\BlogCategory;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class BlogCategoryController extends Controller
{
    public function index(Request $request): JsonResponse
    {
        $query = BlogCategory::active()
            ->withCount(['blogs as published_blogs_count' => fn ($q) => $q->where('status', 'published')])
            ->orderBy('sort_order')
            ->orderBy('name');

        if ($request->boolean('with_children')) {
            $query->parents()->with(['children' => fn ($q) => $q->active()
                ->withCount(['blogs as published_blogs_count' => fn ($q2) => $q2->where('status', 'published')])
                ->orderBy('sort_order')]);
        }

        return response()->json([
            'success' => true,
            'data'    => $query->get(),
        ]);
    }

    public function show(string $slug, Request $request): JsonResponse
    {
        $category = BlogCategory::active()
            ->where('slug', $slug)
            ->withCount(['blogs as published_blogs_count' => fn ($q) => $q->where('status', 'published')])
            ->firstOrFail();

        $perPage = min((int) $request->get('per_page', 10), 50);

        $blogs = $category->blogs()
            ->published()
            ->with(['author:id,name', 'tags:id,name,slug'])
            ->withCount(['comments as approved_comments_count' => fn ($q) => $q->where('status', 'approved')])
            ->orderByDesc('published_at')
            ->paginate($perPage);

        return response()->json([
            'success' => true,
            'data'    => [
                'category' => [
                    'id'                    => $category->id,
                    'name'                  => $category->name,
                    'slug'                  => $category->slug,
                    'description'           => $category->description,
                    'published_blogs_count' => $category->published_blogs_count,
                    'seo'                   => [
                        'meta_title'       => $category->meta_title ?? $category->name,
                        'meta_description' => $category->meta_description,
                        'og_image'         => $category->og_image,
                    ],
                ],
                'blogs' => $blogs->items(),
            ],
            'meta' => [
                'current_page' => $blogs->currentPage(),
                'last_page'    => $blogs->lastPage(),
                'per_page'     => $blogs->perPage(),
                'total'        => $blogs->total(),
            ],
        ]);
    }
}