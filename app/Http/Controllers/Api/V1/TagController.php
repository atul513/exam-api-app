<?php
namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Models\Tag;
use Illuminate\Http\Request;
use Illuminate\Http\JsonResponse;

class TagController extends Controller
{
    /**
     * GET /api/v1/tags
     * List all tags, optionally filtered by category.
     */
    public function index(Request $request): JsonResponse
    {
        $query = Tag::query()->withCount('questions');

        if ($request->filled('category')) {
            $query->where('category', $request->category);
        }

        if ($request->filled('search')) {
            $query->where('name', 'like', '%' . $request->search . '%');
        }

        $tags = $query->orderBy('name')->get()->map(fn($t) => [
            'id'              => $t->id,
            'name'            => $t->name,
            'slug'            => $t->slug,
            'category'        => $t->category,
            'questions_count' => $t->questions_count,
        ]);

        return response()->json(['data' => $tags]);
    }

    /**
     * POST /api/v1/tags
     */
    public function store(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'name'     => 'required|string|max:100',
            'slug'     => 'nullable|string|max:100|unique:tags,slug|alpha_dash',
            'category' => 'nullable|string|max:50',
        ]);

        $tag = Tag::create($validated);

        return response()->json([
            'message' => 'Tag created.',
            'data'    => $tag,
        ], 201);
    }

    /**
     * DELETE /api/v1/tags/{tag}
     */
    public function destroy(Tag $tag): JsonResponse
    {
        // Detach from all questions first, then delete
        $tag->questions()->detach();
        $tag->delete();

        return response()->json(['message' => 'Tag deleted.']);
    }
}

