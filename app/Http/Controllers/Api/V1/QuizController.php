<?php
// ─────────────────────────────────────────────────────────────
// FILE: app/Http/Controllers/Api/V1/QuizController.php
// ─────────────────────────────────────────────────────────────

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Models\{Quiz, QuizLeaderboard};
use App\Services\QuizService;
use App\Enums\QuizStatus;
use Illuminate\Http\Request;
use Illuminate\Http\JsonResponse;

class QuizController extends Controller
{
    public function __construct(private QuizService $quizService) {}

    public function index(Request $request): JsonResponse
    {
        $quizzes = $this->quizService->list($request->all());
        return response()->json($quizzes);
    }

    public function store(Request $request): JsonResponse
    {
        $data = $request->validate([
            // Details
            'title'             => 'required|string|max:255',
            'category_id'       => 'nullable|exists:quiz_categories,id',
            'type'              => 'required|in:quiz,exam',
            'access_type'       => 'required|in:free,paid',
            'price'             => 'nullable|numeric|min:0|required_if:access_type,paid',
            'description'       => 'nullable|string|max:5000',
            'visibility'        => 'required|in:public,private',
            // Settings
            'duration_mode'     => 'required|in:manual,auto',
            'total_duration_min' => 'nullable|integer|min:1|required_if:duration_mode,manual',
            'marks_mode'        => 'required|in:question_wise,fixed',
            'fixed_marks_per_question' => 'nullable|numeric|min:0.01|required_if:marks_mode,fixed',
            'negative_marking'  => 'required|boolean',
            'negative_marks_per_question' => 'nullable|numeric|min:0',
            'pass_percentage'   => 'required|numeric|min:0|max:100',
            'shuffle_questions' => 'nullable|boolean',
            'shuffle_options'   => 'nullable|boolean',
            'max_attempts'      => 'nullable|integer|min:1',
            'disable_finish_button'      => 'nullable|boolean',
            'enable_question_list_view'  => 'nullable|boolean',
            'hide_solutions'             => 'nullable|boolean',
            'show_leaderboard'           => 'nullable|boolean',
            'show_result_immediately'    => 'nullable|boolean',
            'allow_review_after_submit'  => 'nullable|boolean',
            'auto_submit_on_timeout'     => 'nullable|boolean',
            // Questions
            'questions'                    => 'nullable|array',
            'questions.*.question_id'      => 'required|exists:questions,id',
            'questions.*.section_id'       => 'nullable|integer',
            'questions.*.sort_order'       => 'nullable|integer',
            'questions.*.marks_override'   => 'nullable|numeric|min:0',
            // Sections
            'sections'                     => 'nullable|array',
            'sections.*.title'             => 'required|string|max:255',
            'sections.*.instructions'      => 'nullable|string',
            'sections.*.sort_order'        => 'nullable|integer',
            // Schedules
            'schedules'                    => 'nullable|array',
            'schedules.*.title'            => 'nullable|string|max:255',
            'schedules.*.starts_at'        => 'required|date',
            'schedules.*.ends_at'          => 'required|date|after:schedules.*.starts_at',
            'schedules.*.grace_period_min' => 'nullable|integer|min:0',
            'schedules.*.user_group_ids'   => 'nullable|array',
        ]);

        $quiz = $this->quizService->create($data, $request->user()->id);

        return response()->json([
            'message' => 'Quiz created successfully.',
            'data'    => $quiz,
        ], 201);
    }

    public function show(Quiz $quiz): JsonResponse
    {
        $quiz->load([
            'category', 'creator:id,name', 'sections',
            'quizQuestions' => fn($q) => $q->with([
                'question' => fn($q2) => $q2->with(['subject:id,name', 'topic:id,name', 'options']),
                'section:id,title',
            ]),
            'schedules' => fn($q) => $q->with('userGroups:id,name'),
        ])->loadCount('attempts');

        return response()->json(['data' => $quiz]);
    }

    public function update(Request $request, Quiz $quiz): JsonResponse
    {
        $data = $request->validate([
            'title'             => 'sometimes|string|max:255',
            'category_id'       => 'nullable|exists:quiz_categories,id',
            'type'              => 'sometimes|in:quiz,exam',
            'access_type'       => 'sometimes|in:free,paid',
            'price'             => 'nullable|numeric|min:0',
            'description'       => 'nullable|string|max:5000',
            'visibility'        => 'sometimes|in:public,private',
            'duration_mode'     => 'sometimes|in:manual,auto',
            'total_duration_min' => 'nullable|integer|min:1',
            'marks_mode'        => 'sometimes|in:question_wise,fixed',
            'fixed_marks_per_question' => 'nullable|numeric',
            'negative_marking'  => 'sometimes|boolean',
            'pass_percentage'   => 'sometimes|numeric|min:0|max:100',
            'shuffle_questions' => 'nullable|boolean',
            'shuffle_options'   => 'nullable|boolean',
            'max_attempts'      => 'nullable|integer|min:1',
            // ... same boolean settings as store
            'questions'         => 'nullable|array',
            'questions.*.question_id' => 'required|exists:questions,id',
            'sections'          => 'nullable|array',
            'schedules'         => 'nullable|array',
            'schedules.*.starts_at' => 'required|date',
            'schedules.*.ends_at'   => 'required|date|after:schedules.*.starts_at',
        ]);

        $quiz = $this->quizService->update($quiz, $data);

        return response()->json(['message' => 'Quiz updated.', 'data' => $quiz]);
    }

    public function destroy(Quiz $quiz): JsonResponse
    {
        $quiz->delete();
        return response()->json(['message' => 'Quiz archived.']);
    }

    public function publish(Quiz $quiz): JsonResponse
    {
        try {
            $this->quizService->publish($quiz);
            return response()->json(['message' => 'Quiz published.']);
        } catch (\Exception $e) {
            return response()->json(['message' => $e->getMessage()], 422);
        }
    }

    public function archive(Quiz $quiz): JsonResponse
    {
        $quiz->update(['status' => QuizStatus::Archived]);
        return response()->json(['message' => 'Quiz archived.']);
    }

    public function questions(Quiz $quiz): JsonResponse
    {
        $questions = $quiz->quizQuestions()
            ->with(['question.subject:id,name', 'question.topic:id,name', 'question.options', 'section:id,title'])
            ->orderBy('sort_order')
            ->get();

        return response()->json(['data' => $questions]);
    }

    public function schedules(Quiz $quiz): JsonResponse
    {
        return response()->json([
            'data' => $quiz->schedules()->with('userGroups:id,name')->get(),
        ]);
    }

    public function leaderboard(Quiz $quiz): JsonResponse
    {
        $entries = QuizLeaderboard::where('quiz_id', $quiz->id)
            ->with('user:id,name,email')
            ->orderBy('rank')
            ->paginate(50);

        return response()->json($entries);
    }
}
