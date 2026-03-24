<?php

namespace App\Http\Controllers\Api\Admin;

use App\Http\Controllers\Controller;
use App\Models\BlogComment;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class BlogCommentController extends Controller
{
    public function index(Request $request): JsonResponse
    {
        $query = BlogComment::with(['blog:id,title,slug', 'user:id,name'])
            ->orderByDesc('created_at');

        if ($request->filled('status')) {
            $query->where('status', $request->status);
        }
        if ($request->filled('blog_id')) {
            $query->where('blog_id', $request->blog_id);
        }
        if ($request->filled('search')) {
            $query->where('content', 'like', "%{$request->search}%");
        }

        $perPage = min((int) $request->get('per_page', 20), 100);

        return response()->json([
            'success' => true,
            'data'    => $query->paginate($perPage),
        ]);
    }

    public function show(int $id): JsonResponse
    {
        $comment = BlogComment::with(['blog:id,title,slug', 'user:id,name', 'parent', 'replies'])
            ->findOrFail($id);

        return response()->json([
            'success' => true,
            'data'    => $comment,
        ]);
    }

    public function updateStatus(Request $request, int $id): JsonResponse
    {
        $comment   = BlogComment::findOrFail($id);
        $validated = $request->validate([
            'status' => 'required|in:pending,approved,spam,rejected',
        ]);

        $comment->update($validated);

        return response()->json([
            'success' => true,
            'message' => "Comment status updated to {$validated['status']}.",
            'data'    => ['id' => $comment->id, 'status' => $comment->status],
        ]);
    }

    public function destroy(int $id): JsonResponse
    {
        BlogComment::findOrFail($id)->delete();

        return response()->json([
            'success' => true,
            'message' => 'Comment deleted.',
        ]);
    }

    public function bulkUpdateStatus(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'ids'    => 'required|array|min:1',
            'ids.*'  => 'integer|exists:blog_comments,id',
            'status' => 'required|in:pending,approved,spam,rejected',
        ]);

        $count = BlogComment::whereIn('id', $validated['ids'])->update(['status' => $validated['status']]);

        return response()->json([
            'success' => true,
            'message' => "{$count} comment(s) updated to {$validated['status']}.",
        ]);
    }
}