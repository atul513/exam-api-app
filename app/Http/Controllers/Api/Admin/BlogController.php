<?php

namespace App\Http\Controllers\Api\Admin;

use App\Http\Controllers\Controller;
use App\Models\Blog;
use App\Models\BlogTag;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Str;

class BlogController extends Controller
{
    public function index(Request $request): JsonResponse
    {
        $query = Blog::when($request->boolean('with_trashed'), fn ($q) => $q->withTrashed())
            ->with(['author:id,name', 'category:id,name,slug', 'tags:id,name,slug'])
            ->withCount('comments');

        if ($request->filled('status')) {
            $query->where('status', $request->status);
        }
        if ($request->filled('category_id')) {
            $query->where('category_id', $request->category_id);
        }
        if ($request->filled('author_id')) {
            $query->where('user_id', $request->author_id);
        }
        if ($request->boolean('featured')) {
            $query->where('is_featured', true);
        }
        if ($request->filled('search')) {
            $query->search($request->search);
        }

        match ($request->get('sort', 'latest')) {
            'popular' => $query->orderByDesc('views_count'),
            'oldest'  => $query->orderBy('created_at'),
            default   => $query->orderByDesc('created_at'),
        };

        $perPage = min((int) $request->get('per_page', 15), 100);

        return response()->json([
            'success' => true,
            'data'    => $query->paginate($perPage),
        ]);
    }

    public function show(int $id): JsonResponse
    {
        $blog = Blog::withTrashed()
            ->with(['author:id,name', 'category', 'tags'])
            ->withCount('comments')
            ->findOrFail($id);

        return response()->json([
            'success' => true,
            'data'    => $blog,
        ]);
    }

    public function store(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'title'              => 'required|string|max:255',
            'slug'               => 'nullable|string|unique:blogs,slug|max:255',
            'excerpt'            => 'nullable|string|max:500',
            'content'            => 'required|string',
            'category_id'        => 'nullable|exists:blog_categories,id',
            'tags'               => 'nullable|array',
            'tags.*'             => 'string|max:50',
            'featured_image'     => 'nullable|string|max:500',
            'featured_image_alt' => 'nullable|string|max:255',
            'status'             => 'nullable|in:draft,published,scheduled,archived',
            'published_at'       => 'nullable|date',
            'is_featured'        => 'nullable|boolean',
            'allow_comments'     => 'nullable|boolean',
            'meta_title'         => 'nullable|string|max:60',
            'meta_description'   => 'nullable|string|max:160',
            'meta_keywords'      => 'nullable|string|max:255',
            'og_title'           => 'nullable|string|max:60',
            'og_description'     => 'nullable|string|max:200',
            'og_image'           => 'nullable|string|max:500',
            'canonical_url'      => 'nullable|url|max:500',
            'robots' => 'nullable',
            'schema_markup'      => 'nullable|array',
        ]);

        $validated['user_id']      = $request->user()->id;
        $validated['status']       = $validated['status'] ?? 'draft';
        $validated['published_at'] = $validated['published_at']
            ?? ($validated['status'] === 'published' ? now() : null);

        $tags = $validated['tags'] ?? null;
        unset($validated['tags']);

        $blog = Blog::create($validated);

        if ($tags) {
            $blog->tags()->sync($this->resolveTagIds($tags));
        }

        $blog->load(['author:id,name', 'category:id,name,slug', 'tags:id,name,slug']);

        return response()->json([
            'success' => true,
            'message' => 'Blog created successfully.',
            'data'    => $blog,
        ], 201);
    }

    public function update(Request $request, int $id): JsonResponse
    {
        $blog = Blog::findOrFail($id);

        $validated = $request->validate([
            'title'              => 'sometimes|required|string|max:255',
            'slug'               => "nullable|string|unique:blogs,slug,{$id}|max:255",
            'excerpt'            => 'nullable|string|max:500',
            'content'            => 'sometimes|required|string',
            'category_id'        => 'nullable|exists:blog_categories,id',
            'tags'               => 'nullable|array',
            'tags.*'             => 'string|max:50',
            'featured_image'     => 'nullable|string|max:500',
            'featured_image_alt' => 'nullable|string|max:255',
            'status'             => 'nullable|in:draft,published,scheduled,archived',
            'published_at'       => 'nullable|date',
            'is_featured'        => 'nullable|boolean',
            'allow_comments'     => 'nullable|boolean',
            'meta_title'         => 'nullable|string|max:60',
            'meta_description'   => 'nullable|string|max:160',
            'meta_keywords'      => 'nullable|string|max:255',
            'og_title'           => 'nullable|string|max:60',
            'og_description'     => 'nullable|string|max:200',
            'og_image'           => 'nullable|string|max:500',
            'canonical_url'      => 'nullable|url|max:500',
            'robots'             => 'nullable|in:index,follow,noindex,follow,index,nofollow,noindex,nofollow',
            'schema_markup'      => 'nullable|array',
        ]);

        // Auto-set published_at when first publishing
        if (isset($validated['status']) && $validated['status'] === 'published' && ! $blog->published_at) {
            $validated['published_at'] = $validated['published_at'] ?? now();
        }

        $tags = array_key_exists('tags', $validated) ? $validated['tags'] : false;
        unset($validated['tags']);

        $blog->update($validated);

        if ($tags !== false) {
            $blog->tags()->sync($tags ? $this->resolveTagIds($tags) : []);
        }

        $blog->load(['author:id,name', 'category:id,name,slug', 'tags:id,name,slug']);

        return response()->json([
            'success' => true,
            'message' => 'Blog updated successfully.',
            'data'    => $blog,
        ]);
    }

    public function destroy(int $id): JsonResponse
    {
        $blog = Blog::findOrFail($id);
        $blog->delete();

        return response()->json([
            'success' => true,
            'message' => 'Blog moved to trash.',
        ]);
    }

    public function restore(int $id): JsonResponse
    {
        $blog = Blog::withTrashed()->findOrFail($id);
        $blog->restore();

        return response()->json([
            'success' => true,
            'message' => 'Blog restored successfully.',
            'data'    => $blog,
        ]);
    }

    public function forceDelete(int $id): JsonResponse
    {
        $blog = Blog::withTrashed()->findOrFail($id);
        $blog->tags()->detach();
        $blog->comments()->forceDelete();
        $blog->forceDelete();

        return response()->json([
            'success' => true,
            'message' => 'Blog permanently deleted.',
        ]);
    }

    public function updateStatus(Request $request, int $id): JsonResponse
    {
        $blog      = Blog::findOrFail($id);
        $validated = $request->validate([
            'status' => 'required|in:draft,published,scheduled,archived',
        ]);

        if ($validated['status'] === 'published' && ! $blog->published_at) {
            $validated['published_at'] = now();
        }

        $blog->update($validated);

        return response()->json([
            'success' => true,
            'message' => "Blog status updated to {$validated['status']}.",
            'data'    => ['status' => $blog->status, 'published_at' => $blog->published_at],
        ]);
    }

    public function toggleFeatured(int $id): JsonResponse
    {
        $blog = Blog::findOrFail($id);
        $blog->update(['is_featured' => ! $blog->is_featured]);

        return response()->json([
            'success' => true,
            'message' => $blog->is_featured ? 'Blog marked as featured.' : 'Blog removed from featured.',
            'data'    => ['is_featured' => $blog->is_featured],
        ]);
    }

    private function resolveTagIds(array $tagNames): array
    {
        return array_map(function ($name) {
            return BlogTag::firstOrCreate(
                ['slug' => Str::slug($name)],
                ['name' => $name]
            )->id;
        }, $tagNames);
    }
}
