<?php

namespace App\Http\Controllers\Api\Blog;

use App\Http\Controllers\Controller;
use App\Models\BlogTag;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class BlogTagController extends Controller
{
    public function index(): JsonResponse
    {
        $tags = BlogTag::withCount(['blogs as published_blogs_count' => fn ($q) => $q->where('status', 'published')])
            ->orderBy('name')
            ->get();

        return response()->json([
            'success' => true,
            'data'    => $tags,
        ]);
    }

    public function show(string $slug, Request $request): JsonResponse
    {
        $tag = BlogTag::where('slug', $slug)
            ->withCount(['blogs as published_blogs_count' => fn ($q) => $q->where('status', 'published')])
            ->firstOrFail();

        $perPage = min((int) $request->get('per_page', 10), 50);

        $blogs = $tag->blogs()
            ->published()
            ->with(['author:id,name', 'category:id,name,slug'])
            ->withCount(['comments as approved_comments_count' => fn ($q) => $q->where('status', 'approved')])
            ->orderByDesc('published_at')
            ->paginate($perPage);

        return response()->json([
            'success' => true,
            'data'    => [
                'tag'   => [
                    'id'                    => $tag->id,
                    'name'                  => $tag->name,
                    'slug'                  => $tag->slug,
                    'published_blogs_count' => $tag->published_blogs_count,
                    'seo'                   => [
                        'meta_title'       => $tag->meta_title ?? $tag->name,
                        'meta_description' => $tag->meta_description,
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