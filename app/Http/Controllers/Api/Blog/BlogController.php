<?php

namespace App\Http\Controllers\Api\Blog;

use App\Http\Controllers\Controller;
use App\Models\Blog;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class BlogController extends Controller
{
    public function index(Request $request): JsonResponse
    {
        $query = Blog::published()
            ->with(['author:id,name', 'category:id,name,slug', 'tags:id,name,slug'])
            ->withCount(['comments as approved_comments_count' => fn ($q) => $q->where('status', 'approved')]);

        if ($request->filled('category')) {
            $query->byCategory($request->category);
        }
        if ($request->filled('tag')) {
            $query->byTag($request->tag);
        }
        if ($request->boolean('featured')) {
            $query->featured();
        }
        if ($request->filled('author')) {
            $query->where('user_id', $request->author);
        }
        if ($request->filled('search')) {
            $query->search($request->search);
        }

        match ($request->get('sort', 'latest')) {
            'popular' => $query->orderByDesc('views_count'),
            'oldest'  => $query->orderBy('published_at'),
            default   => $query->orderByDesc('published_at'),
        };

        $perPage = min((int) $request->get('per_page', 10), 50);
        $blogs   = $query->paginate($perPage);

        return response()->json([
            'success' => true,
            'data'    => $blogs->through(fn ($blog) => $this->listResource($blog)),
            'meta'    => [
                'current_page' => $blogs->currentPage(),
                'last_page'    => $blogs->lastPage(),
                'per_page'     => $blogs->perPage(),
                'total'        => $blogs->total(),
            ],
        ]);
    }

    public function show(string $slug): JsonResponse
    {
        $blog = Blog::published()
            ->where('slug', $slug)
            ->with(['author:id,name', 'category:id,name,slug,meta_title,meta_description', 'tags:id,name,slug'])
            ->withCount(['comments as approved_comments_count' => fn ($q) => $q->where('status', 'approved')])
            ->firstOrFail();

        $blog->incrementViews();

        $schema = $blog->schema_markup ?? $blog->generateSchemaMarkup();

        return response()->json([
            'success' => true,
            'data'    => [
                'id'                      => $blog->id,
                'title'                   => $blog->title,
                'slug'                    => $blog->slug,
                'excerpt'                 => $blog->excerpt,
                'content'                 => $blog->content,
                'featured_image'          => $blog->featured_image,
                'featured_image_alt'      => $blog->featured_image_alt,
                'status'                  => $blog->status,
                'published_at'            => $blog->published_at,
                'views_count'             => $blog->views_count,
                'reading_time'            => $blog->reading_time,
                'is_featured'             => $blog->is_featured,
                'allow_comments'          => $blog->allow_comments,
                'approved_comments_count' => $blog->approved_comments_count,
                'author'                  => $blog->author,
                'category'                => $blog->category,
                'tags'                    => $blog->tags,
                'seo'                     => [
                    'meta_title'       => $blog->meta_title,
                    'meta_description' => $blog->meta_description,
                    'meta_keywords'    => $blog->meta_keywords,
                    'og_title'         => $blog->og_title,
                    'og_description'   => $blog->og_description,
                    'og_image'         => $blog->og_image ?? $blog->featured_image,
                    'canonical_url'    => $blog->canonical_url,
                    'robots'           => $blog->robots,
                    'schema_markup'    => $schema,
                ],
                'created_at' => $blog->created_at,
                'updated_at' => $blog->updated_at,
            ],
        ]);
    }

    public function related(string $slug): JsonResponse
    {
        $blog = Blog::published()->where('slug', $slug)->with('tags:id')->firstOrFail();

        $related = Blog::published()
            ->where('id', '!=', $blog->id)
            ->where(function ($q) use ($blog) {
                if ($blog->category_id) {
                    $q->where('category_id', $blog->category_id);
                }
                if ($blog->tags->isNotEmpty()) {
                    $q->orWhereHas('tags', fn ($t) => $t->whereIn('blog_tags.id', $blog->tags->pluck('id')));
                }
            })
            ->with(['author:id,name', 'category:id,name,slug', 'tags:id,name,slug'])
            ->orderByDesc('published_at')
            ->limit(5)
            ->get()
            ->map(fn ($b) => $this->listResource($b));

        return response()->json([
            'success' => true,
            'data'    => $related,
        ]);
    }

    private function listResource(Blog $blog): array
    {
        return [
            'id'                      => $blog->id,
            'title'                   => $blog->title,
            'slug'                    => $blog->slug,
            'excerpt'                 => $blog->excerpt,
            'featured_image'          => $blog->featured_image,
            'featured_image_alt'      => $blog->featured_image_alt,
            'published_at'            => $blog->published_at,
            'views_count'             => $blog->views_count,
            'reading_time'            => $blog->reading_time,
            'is_featured'             => $blog->is_featured,
            'approved_comments_count' => $blog->approved_comments_count ?? 0,
            'author'                  => $blog->author,
            'category'                => $blog->category,
            'tags'                    => $blog->tags,
            'seo'                     => [
                'meta_title'       => $blog->meta_title,
                'meta_description' => $blog->meta_description,
                'og_title'         => $blog->og_title,
                'og_image'         => $blog->og_image ?? $blog->featured_image,
                'robots'           => $blog->robots,
            ],
        ];
    }
}