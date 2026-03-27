<?php
// ─────────────────────────────────────────────────────────────
// FILE: app/Http/Controllers/Api/V1/PracticeSetController.php
// ─────────────────────────────────────────────────────────────

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Models\{PracticeSet, UserRewardPoint};
use App\Services\PracticeSetService;
use Illuminate\Http\Request;
use Illuminate\Http\JsonResponse;
use App\Models\PracticeSetProgress;
class PracticeSetController extends Controller
{
    public function __construct(private PracticeSetService $service) {}

    /**
     * GET /api/v1/practice-sets
     */
    public function index(Request $request): JsonResponse
    {
        $sets = $this->service->list($request->all());
        return response()->json($sets);
    }

    /**
     * POST /api/v1/practice-sets
     */
    public function store(Request $request): JsonResponse
    {
        $data = $request->validate([
            'title'                => 'required|string|max:255',
            'category_id'          => 'nullable|exists:quiz_categories,id',
            'subject_id'           => 'nullable|exists:subjects,id',
            'topic_id'             => 'nullable|exists:topics,id',
            'description'          => 'nullable|string|max:5000',
            'thumbnail_url'        => 'nullable|url|max:500',
            'access_type'          => 'required|in:free,paid',
            'price'                => 'nullable|numeric|min:0|required_if:access_type,paid',
            'status'               => 'nullable|in:draft,published',
            'allow_reward_points'  => 'required|boolean',
            'points_mode'          => 'required_if:allow_reward_points,true|in:auto,manual',
            'points_per_question'  => 'nullable|integer|min:1|required_if:points_mode,manual',
            'show_reward_popup'    => 'nullable|boolean',
            'questions'                    => 'nullable|array',
            'questions.*.question_id'      => 'required|exists:questions,id',
            'questions.*.sort_order'       => 'nullable|integer',
            'questions.*.points_override'  => 'nullable|integer|min:0',
        ]);

        $set = $this->service->create($data, $request->user()->id);

        return response()->json([
            'message' => 'Practice set created.',
            'data'    => $set,
        ], 201);
    }

    /**
     * GET /api/v1/practice-sets/{practiceSet}
     */
    public function show(Request $request, PracticeSet $practiceSet): JsonResponse
    {
        $practiceSet->load([
            'category:id,name', 'subject:id,name,code', 'topic:id,name,code',
            'creator:id,name',
            'practiceSetQuestions' => fn($q) => $q->with([
                'question' => fn($q2) => $q2->with(['subject:id,name', 'topic:id,name']),
            ]),
        ]);

        // Attach user progress if authenticated
        $data = $practiceSet->toArray();
        if ($request->user()) {
            $data['my_progress'] = $practiceSet->getUserProgress($request->user()->id);
        }

        return response()->json(['data' => $data]);
    }

    /**
     * PUT /api/v1/practice-sets/{practiceSet}
     */
    public function update(Request $request, PracticeSet $practiceSet): JsonResponse
    {
        $data = $request->validate([
            'title'                => 'sometimes|string|max:255',
            'category_id'          => 'nullable|exists:quiz_categories,id',
            'subject_id'           => 'nullable|exists:subjects,id',
            'topic_id'             => 'nullable|exists:topics,id',
            'description'          => 'nullable|string|max:5000',
            'access_type'          => 'sometimes|in:free,paid',
            'price'                => 'nullable|numeric|min:0',
            'status'               => 'sometimes|in:draft,published',
            'allow_reward_points'  => 'sometimes|boolean',
            'points_mode'          => 'sometimes|in:auto,manual',
            'points_per_question'  => 'nullable|integer|min:1',
            'show_reward_popup'    => 'nullable|boolean',
            'questions'                    => 'nullable|array',
            'questions.*.question_id'      => 'required|exists:questions,id',
            'questions.*.sort_order'       => 'nullable|integer',
            'questions.*.points_override'  => 'nullable|integer|min:0',
        ]);

        $set = $this->service->update($practiceSet, $data);

        return response()->json(['message' => 'Practice set updated.', 'data' => $set]);
    }

    /**
     * DELETE /api/v1/practice-sets/{practiceSet}
     */
    public function destroy(PracticeSet $practiceSet): JsonResponse
    {
        $practiceSet->delete();
        return response()->json(['message' => 'Practice set deleted.']);
    }

    /**
     * POST /api/v1/practice-sets/{practiceSet}/publish
     */
    public function publish(PracticeSet $practiceSet): JsonResponse
    {
        if ($practiceSet->total_questions === 0) {
            return response()->json(['message' => 'Cannot publish with no questions.'], 422);
        }

        $practiceSet->update(['status' => 'published']);

        return response()->json(['message' => 'Practice set published.']);
    }

    /**
     * GET /api/v1/practice-sets/{practiceSet}/questions
     */
    public function questions(PracticeSet $practiceSet): JsonResponse
    {
        $questions = $practiceSet->practiceSetQuestions()
            ->with(['question' => fn($q) => $q->with([
                'subject:id,name', 'topic:id,name', 'options', 'blanks', 'matchPairs',
            ])])
            ->orderBy('sort_order')
            ->get();

        return response()->json(['data' => $questions]);
    }

    /**
     * GET /api/v1/practice-sets/{practiceSet}/start
     * Load questions for practice (no timer, show one at a time).
     */
    public function start(Request $request, PracticeSet $practiceSet): JsonResponse
    {
        if (!$practiceSet->isPublished()) {
            return response()->json(['message' => 'Practice set is not published.'], 403);
        }

        $userId = $request->user()->id;

        $questions = $practiceSet->practiceSetQuestions()
            ->with(['question' => function ($q) {
                $q->with([
                    'options:id,question_id,option_text,option_media,sort_order',
                    'blanks:id,question_id,blank_number',
                    'matchPairs:id,question_id,column_a_text,column_b_text,sort_order',
                ]);
                $q->select('id', 'type', 'question_text', 'question_media', 'marks');
            }])
            ->orderBy('sort_order')
            ->get();

        // Get existing progress
        $progress = PracticeSetProgress::where('practice_set_id', $practiceSet->id)
            ->where('user_id', $userId)
            ->get()
            ->keyBy('question_id');

        return response()->json([
            'data' => [
                'practice_set' => [
                    'id'                   => $practiceSet->id,
                    'title'                => $practiceSet->title,
                    'total_questions'      => $practiceSet->total_questions,
                    'allow_reward_points'  => $practiceSet->allow_reward_points,
                    'show_reward_popup'    => $practiceSet->show_reward_popup,
                ],
                'questions' => $questions,
                'progress'  => $progress->map(fn($p) => [
                    'question_id'         => $p->question_id,
                    'is_correct'          => $p->is_correct,
                    'points_earned'       => $p->points_earned,
                    'attempts'            => $p->attempts,
                    'selected_option_ids' => $p->selected_option_ids,
                    'text_answer'         => $p->text_answer,
                    'fill_blank_answers'  => $p->fill_blank_answers,
                    'match_pairs_answer'  => $p->match_pairs_answer,
                ]),
                'summary' => $practiceSet->getUserProgress($userId),
            ],
        ]);
    }

    /**
     * POST /api/v1/practice-sets/{practiceSet}/check-answer
     * Instant grading — submit one question, get immediate feedback.
     */
    public function checkAnswer(Request $request, PracticeSet $practiceSet): JsonResponse
    {
        if (!$practiceSet->isPublished()) {
            return response()->json(['message' => 'Practice set is not published.'], 403);
        }

        $data = $request->validate([
            'question_id'         => 'required|integer',
            'selected_option_ids' => 'nullable|array',
            'text_answer'         => 'nullable|string|max:50000',
            'fill_blank_answers'  => 'nullable|array',
            'match_pairs_answer'  => 'nullable|array',
        ]);

        $result = $this->service->checkAnswer(
            $practiceSet,
            $request->user()->id,
            $data
        );

        return response()->json(['data' => $result]);
    }

    /**
     * GET /api/v1/practice-sets/{practiceSet}/progress
     * Get user's progress summary.
     */
    public function progress(Request $request, PracticeSet $practiceSet): JsonResponse
    {
        $userId = $request->user()->id;

        $summary = $practiceSet->getUserProgress($userId);

        $details = PracticeSetProgress::where('practice_set_id', $practiceSet->id)
            ->where('user_id', $userId)
            ->with('question:id,type,question_text')
            ->get()
            ->map(fn($p) => [
                'question_id'   => $p->question_id,
                'question_text' => $p->question?->question_text,
                'question_type' => $p->question?->type,
                'is_correct'    => $p->is_correct,
                'points_earned' => $p->points_earned,
                'attempts'      => $p->attempts,
            ]);

        return response()->json([
            'data' => [
                'summary' => $summary,
                'details' => $details,
            ],
        ]);
    }

    /**
     * GET /api/v1/my/reward-points
     */
    public function myRewardPoints(Request $request): JsonResponse
    {
        $userId = $request->user()->id;
        $total = UserRewardPoint::totalForUser($userId);

        $history = UserRewardPoint::where('user_id', $userId)
            ->orderByDesc('created_at')
            ->paginate(20);

        return response()->json([
            'total_points' => $total,
            'history'      => $history,
        ]);
    }
}
