<?php

// ============================================================
// ─── app/Http/Controllers/Api/V1/QuestionController.php ─────
// ============================================================

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Http\Requests\StoreQuestionRequest;
use App\Http\Resources\QuestionResource;
use App\Http\Resources\QuestionCollection;
use App\Models\Question;
use App\Services\QuestionService;
use App\Enums\QuestionStatus;
use Illuminate\Http\Request;
use Illuminate\Http\JsonResponse;

class QuestionController extends Controller
{
    public function __construct(
        private QuestionService $questionService
    ) {}

    /**
     * GET /api/v1/questions
     * List questions with filters + pagination.
     */
    public function index(Request $request): QuestionCollection
    {
        $questions = $this->questionService->list($request->all());

        return new QuestionCollection($questions);
    }

    /**
     * POST /api/v1/questions
     * Create a new question.
     */
    public function store(StoreQuestionRequest $request): JsonResponse
    {
        $question = $this->questionService->create(
            $request->validated(),
            $request->user()->id
        );

        return response()->json([
            'message' => 'Question created successfully.',
            'data'    => new QuestionResource($question),
        ], 201);
    }

    /**
     * GET /api/v1/questions/{question}
     * Get single question with all details.
     */
    public function show(Question $question): QuestionResource
    {
        $question = $this->questionService->show($question);

        return new QuestionResource($question);
    }

    /**
     * PUT /api/v1/questions/{question}
     * Update a question.
     */
    public function update(StoreQuestionRequest $request, Question $question): JsonResponse
    {
        $question = $this->questionService->update(
            $question,
            $request->validated(),
            $request->user()->id
        );

        return response()->json([
            'message' => 'Question updated successfully.',
            'data'    => new QuestionResource($question),
        ]);
    }

    /**
     * DELETE /api/v1/questions/{question}
     * Soft delete (archive).
     */
    public function destroy(Question $question): JsonResponse
    {
        $this->questionService->changeStatus(
            $question,
            QuestionStatus::Archived,
            request()->user()->id
        );
        $question->delete();

        return response()->json(['message' => 'Question archived.']);
    }

    /**
     * POST /api/v1/questions/{question}/clone
     */
    public function clone(Question $question): JsonResponse
    {
        $cloned = $this->questionService->clone($question, request()->user()->id);

        return response()->json([
            'message' => 'Question cloned as draft.',
            'data'    => new QuestionResource($cloned),
        ], 201);
    }

    /**
     * POST /api/v1/questions/{question}/submit-review
     */
    public function submitReview(Question $question): JsonResponse
    {
        $question = $this->questionService->changeStatus(
            $question, QuestionStatus::Review, request()->user()->id
        );

        return response()->json([
            'message' => 'Question submitted for review.',
            'data'    => new QuestionResource($question),
        ]);
    }

    /**
     * POST /api/v1/questions/{question}/approve
     */
    public function approve(Request $request, Question $question): JsonResponse
    {
        $question = $this->questionService->changeStatus(
            $question, QuestionStatus::Approved, $request->user()->id
        );

        return response()->json([
            'message' => 'Question approved.',
            'data'    => new QuestionResource($question),
        ]);
    }

    /**
     * POST /api/v1/questions/{question}/reject
     */
    public function reject(Request $request, Question $question): JsonResponse
    {
        $request->validate(['reason' => 'required|string|max:1000']);

        $question = $this->questionService->changeStatus(
            $question, QuestionStatus::Rejected, $request->user()->id, $request->reason
        );

        return response()->json([
            'message' => 'Question rejected.',
            'data'    => new QuestionResource($question),
        ]);
    }

    /**
     * PATCH /api/v1/questions/bulk-status
     */
    public function bulkStatus(Request $request): JsonResponse
    {
        $request->validate([
            'question_ids'  => 'required|array|min:1|max:500',
            'question_ids.*' => 'integer|exists:questions,id',
            'status'        => 'required|in:approved,rejected,archived,draft',
        ]);

        $count = $this->questionService->bulkChangeStatus(
            $request->question_ids,
            QuestionStatus::from($request->status),
            $request->user()->id
        );

        return response()->json([
            'message' => "{$count} questions updated.",
        ]);
    }
}
