<?php

namespace App\Http\Controllers\Api\Blog;

use App\Http\Controllers\Controller;
use App\Models\Blog;
use App\Models\BlogComment;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class BlogCommentController extends Controller
{
    public function index(string $slug, Request $request): JsonResponse
    {
        $blog = Blog::published()->where('slug', $slug)->firstOrFail();

        $comments = BlogComment::where('blog_id', $blog->id)
            ->approved()
            ->whereNull('parent_id')
            ->with(['user:id,name', 'replies'])
            ->orderByDesc('created_at')
            ->paginate(20);

        return response()->json([
            'success' => true,
            'data'    => $comments->through(fn ($c) => $this->commentResource($c)),
            'meta'    => [
                'current_page' => $comments->currentPage(),
                'last_page'    => $comments->lastPage(),
                'per_page'     => $comments->perPage(),
                'total'        => $comments->total(),
            ],
        ]);
    }

    public function store(Request $request, string $slug): JsonResponse
    {
        $blog = Blog::published()
            ->where('slug', $slug)
            ->where('allow_comments', true)
            ->firstOrFail();

        $user  = $request->user();
        $rules = [
            'content'   => 'required|string|min:3|max:2000',
            'parent_id' => 'nullable|integer|exists:blog_comments,id',
        ];

        if (! $user) {
            $rules['guest_name']    = 'required|string|max:100';
            $rules['guest_email']   = 'required|email|max:150';
            $rules['guest_website'] = 'nullable|url|max:200';
        }

        $validated = $request->validate($rules);

        // Ensure parent_id belongs to this blog and is approved
        if (! empty($validated['parent_id'])) {
            $valid = BlogComment::where('id', $validated['parent_id'])
                ->where('blog_id', $blog->id)
                ->where('status', 'approved')
                ->exists();

            if (! $valid) {
                return response()->json(['success' => false, 'message' => 'Invalid parent comment.'], 422);
            }
        }

        $comment = BlogComment::create([
            'blog_id'       => $blog->id,
            'user_id'       => $user?->id,
            'parent_id'     => $validated['parent_id'] ?? null,
            'guest_name'    => $validated['guest_name'] ?? null,
            'guest_email'   => $validated['guest_email'] ?? null,
            'guest_website' => $validated['guest_website'] ?? null,
            'content'       => $validated['content'],
            'status'        => $user ? 'approved' : 'pending', // auto-approve authenticated users
            'ip_address'    => $request->ip(),
        ]);

        $comment->load('user:id,name');

        return response()->json([
            'success' => true,
            'message' => $user ? 'Comment posted successfully.' : 'Comment submitted and awaiting review.',
            'data'    => $this->commentResource($comment),
        ], 201);
    }

    private function commentResource(BlogComment $comment): array
    {
        return [
            'id'          => $comment->id,
            'content'     => $comment->content,
            'status'      => $comment->status,
            'author_name' => $comment->author_name,
            'user'        => $comment->user ? ['id' => $comment->user->id, 'name' => $comment->user->name] : null,
            'replies'     => $comment->replies?->map(fn ($r) => [
                'id'          => $r->id,
                'content'     => $r->content,
                'author_name' => $r->author_name,
                'user'        => $r->user ? ['id' => $r->user->id, 'name' => $r->user->name] : null,
                'created_at'  => $r->created_at,
            ]),
            'created_at' => $comment->created_at,
        ];
    }
}