<?php
// ─────────────────────────────────────────────────────────────
// FILE: app/Http/Controllers/Api/V1/QuizAttemptController.php
// Student-facing: start, answer, submit, view result
// ─────────────────────────────────────────────────────────────

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Models\{Quiz, QuizAttempt, QuizAttemptAnswer};
use App\Services\GradingService;
use App\Enums\AttemptStatus;
use Illuminate\Http\Request;
use Illuminate\Http\JsonResponse;

class QuizAttemptController extends Controller
{
    public function __construct(private GradingService $gradingService) {}

    /**
     * Check if user can start/resume this quiz.
     */
    public function checkAccess(Request $request, Quiz $quiz): JsonResponse
    {
        $result = $quiz->canUserAttempt($request->user()->id);
        return response()->json($result);
    }

    /**
     * Start a new attempt or resume existing one.
     */
    public function start(Request $request, Quiz $quiz): JsonResponse
    {
        $userId = $request->user()->id;
        $access = $quiz->canUserAttempt($userId);

        if (!$access['allowed'] && !isset($access['resume_attempt_id'])) {
            return response()->json(['message' => $access['reason']], 403);
        }

        // Resume existing
        if (isset($access['resume_attempt_id'])) {
            $attempt = QuizAttempt::with('answers')->find($access['resume_attempt_id']);
            return response()->json([
                'message' => 'Resuming existing attempt.',
                'data'    => $this->formatAttemptForStudent($attempt, $quiz),
            ]);
        }

        // Create new attempt
        $attemptNumber = $quiz->userAttemptCount($userId) + 1;

        // Build question order
        $questionIds = $quiz->quizQuestions()->orderBy('sort_order')->pluck('question_id')->toArray();
        if ($quiz->shuffle_questions) {
            shuffle($questionIds);
        }

        $attempt = QuizAttempt::create([
            'quiz_id'         => $quiz->id,
            'user_id'         => $userId,
            'schedule_id'     => $quiz->getActiveSchedule()?->id,
            'attempt_number'  => $attemptNumber,
            'status'          => AttemptStatus::InProgress,
            'started_at'      => now(),
            'time_allowed_sec' => $quiz->total_duration_min ? $quiz->total_duration_min * 60 : null,
            'question_order'  => $questionIds,
            'ip_address'      => $request->ip(),
            'user_agent'      => $request->userAgent(),
        ]);

        // Pre-create empty answer rows
        foreach ($quiz->quizQuestions as $qq) {
            QuizAttemptAnswer::create([
                'attempt_id'       => $attempt->id,
                'question_id'      => $qq->question_id,
                'quiz_question_id' => $qq->id,
            ]);
        }

        return response()->json([
            'message' => 'Attempt started.',
            'data'    => $this->formatAttemptForStudent($attempt->fresh()->load('answers'), $quiz),
        ], 201);
    }

    /**
     * Save answer for a single question (called on each question navigate).
     */
    public function saveAnswer(Request $request, QuizAttempt $attempt): JsonResponse
    {
        if (!$attempt->isInProgress()) {
            return response()->json(['message' => 'Attempt is not in progress.'], 422);
        }

        if ($attempt->user_id !== $request->user()->id) {
            return response()->json(['message' => 'Unauthorized.'], 403);
        }

        // Auto-submit if time is up
        if ($attempt->isTimeUp()) {
            return $this->autoSubmit($attempt);
        }

        $data = $request->validate([
            'question_id'         => 'required|integer',
            'selected_option_ids' => 'nullable|array',
            'text_answer'         => 'nullable|string|max:50000',
            'fill_blank_answers'  => 'nullable|array',
            'match_pairs_answer'  => 'nullable|array',
            'time_spent_sec'      => 'nullable|integer|min:0',
            'is_bookmarked'       => 'nullable|boolean',
        ]);

        $answer = $attempt->answers()->where('question_id', $data['question_id'])->first();

        if (!$answer) {
            return response()->json(['message' => 'Question not found in this attempt.'], 404);
        }

        $answer->update([
            'selected_option_ids' => $data['selected_option_ids'] ?? $answer->selected_option_ids,
            'text_answer'         => $data['text_answer'] ?? $answer->text_answer,
            'fill_blank_answers'  => $data['fill_blank_answers'] ?? $answer->fill_blank_answers,
            'match_pairs_answer'  => $data['match_pairs_answer'] ?? $answer->match_pairs_answer,
            'time_spent_sec'      => $data['time_spent_sec'] ?? $answer->time_spent_sec,
            'is_bookmarked'       => $data['is_bookmarked'] ?? $answer->is_bookmarked,
            'visit_count'         => $answer->visit_count + 1,
        ]);

        return response()->json(['message' => 'Answer saved.']);
    }

    /**
     * Submit the attempt for grading.
     */
    public function submit(Request $request, QuizAttempt $attempt): JsonResponse
    {
        if (!$attempt->isInProgress()) {
            return response()->json(['message' => 'Attempt already submitted.'], 422);
        }
        if ($attempt->user_id !== $request->user()->id) {
            return response()->json(['message' => 'Unauthorized.'], 403);
        }

        // Bulk-save answers if provided in the submit payload
        if ($request->has('answers')) {
            $data = $request->validate([
                'answers'                        => 'array',
                'answers.*.question_id'          => 'required|integer',
                'answers.*.selected_option_ids'  => 'nullable|array',
                'answers.*.text_answer'          => 'nullable|string|max:50000',
                'answers.*.fill_blank_answers'   => 'nullable|array',
                'answers.*.match_pairs_answer'   => 'nullable|array',
                'answers.*.time_spent_sec'       => 'nullable|integer|min:0',
                'answers.*.is_bookmarked'        => 'nullable|boolean',
            ]);

            foreach ($data['answers'] as $ans) {
                $answer = $attempt->answers()->where('question_id', $ans['question_id'])->first();
                if (!$answer) continue;

                $answer->update([
                    'selected_option_ids' => $ans['selected_option_ids'] ?? $answer->selected_option_ids,
                    'text_answer'         => $ans['text_answer'] ?? $answer->text_answer,
                    'fill_blank_answers'  => $ans['fill_blank_answers'] ?? $answer->fill_blank_answers,
                    'match_pairs_answer'  => $ans['match_pairs_answer'] ?? $answer->match_pairs_answer,
                    'time_spent_sec'      => $ans['time_spent_sec'] ?? $answer->time_spent_sec,
                    'is_bookmarked'       => $ans['is_bookmarked'] ?? $answer->is_bookmarked,
                ]);
            }
        }

        $attempt->update([
            'status'       => AttemptStatus::Submitted,
            'submitted_at' => now(),
            'time_spent_sec' => max(0, (int) $attempt->started_at->diffInSeconds(now())),
        ]);

        // Grade
        $attempt = $this->gradingService->gradeAttempt($attempt);

        return response()->json([
            'message' => 'Quiz submitted and graded.',
            'data'    => $attempt->only([
                'id', 'status', 'final_score', 'percentage', 'is_passed',
                'correct_count', 'incorrect_count', 'skipped_count', 'rank',
            ]),
        ]);
    }

    /**
     * Get current state of an attempt (in-progress or completed).
     */
    public function show(Request $request, QuizAttempt $attempt): JsonResponse
    {
        if ($attempt->user_id !== $request->user()->id) {
            return response()->json(['message' => 'Unauthorized.'], 403);
        }

        $quiz = $attempt->quiz;
        $attempt->load('answers');

        return response()->json([
            'data' => $this->formatAttemptForStudent($attempt, $quiz),
        ]);
    }

    /**
     * Get full result after completion.
     */
    public function result(QuizAttempt $attempt): JsonResponse
    {
        $quiz = $attempt->quiz;

        if (!in_array($attempt->status->value, ['completed', 'grading'])) {
            return response()->json(['message' => 'Result not available yet.'], 422);
        }

        $attempt->load(['answers' => function ($q) use ($quiz) {
            $q->with(['question' => function ($q2) use ($quiz) {
                $q2->with(['options', 'blanks', 'matchPairs', 'expectedAnswer', 'subject:id,name', 'topic:id,name']);
            }, 'quizQuestion']);
        }]);

        // Hide solutions if quiz setting says so
        if ($quiz->hide_solutions) {
            $attempt->answers->each(function ($answer) {
                $answer->question->makeHidden(['explanation', 'solution_approach']);
                $answer->question->options->each(fn($o) => $o->makeHidden('explanation'));
            });
        }

        return response()->json([
            'data' => [
                'attempt'  => $attempt->only([
                    'id', 'attempt_number', 'status', 'started_at', 'submitted_at',
                    'time_spent_sec', 'total_marks', 'marks_obtained',
                    'negative_marks_total', 'final_score', 'percentage',
                    'is_passed', 'rank', 'total_questions', 'attempted_count',
                    'correct_count', 'incorrect_count', 'skipped_count',
                ]),
                'answers'  => $attempt->answers,
                'quiz'     => $quiz->only(['id', 'title', 'pass_percentage', 'show_leaderboard']),
            ],
        ]);
    }

    /**
     * List current user's attempts.
     */
    public function myAttempts(Request $request): JsonResponse
    {
        $attempts = QuizAttempt::where('user_id', $request->user()->id)
            ->with('quiz:id,title,type,total_marks,total_questions')
            ->orderByDesc('created_at')
            ->paginate(20);

        return response()->json($attempts);
    }

    /**
     * List quizzes available to current user.
     */
    public function myQuizzes(Request $request): JsonResponse
    {
        $quizzes = Quiz::published()
            ->public()
            ->withCount(['attempts as my_attempts' => function ($q) use ($request) {
                $q->where('user_id', $request->user()->id);
            }])
            ->orderByDesc('created_at')
            ->paginate(20);

        return response()->json($quizzes);
    }

    // ── HELPERS ──

    private function autoSubmit(QuizAttempt $attempt): JsonResponse
    {
        $attempt->update([
            'status'       => AttemptStatus::AutoSubmitted,
            'submitted_at' => now(),
            'time_spent_sec' => $attempt->time_allowed_sec,
        ]);

        $attempt = $this->gradingService->gradeAttempt($attempt);

        return response()->json([
            'message' => 'Time expired. Quiz auto-submitted.',
            'data'    => $attempt->only(['id', 'status', 'final_score', 'percentage', 'is_passed']),
        ]);
    }

    private function formatAttemptForStudent(QuizAttempt $attempt, Quiz $quiz): array
    {
        $questions = $quiz->quizQuestions()
            ->with(['question' => function ($q) {
                $q->with(['options' => function ($q2) {
                    $q2->select('id', 'question_id', 'option_text', 'option_media', 'sort_order');
                    // Hide is_correct from student during attempt!
                }, 'blanks:id,question_id,blank_number', 'matchPairs:id,question_id,column_a_text,column_b_text,sort_order']);
                $q->select('id', 'type', 'question_text', 'question_media', 'marks', 'time_limit_sec');
            }])
            ->orderByRaw('FIELD(question_id, ' . implode(',', $attempt->question_order ?? [0]) . ')')
            ->get();

        // Shuffle options if enabled
        if ($quiz->shuffle_options) {
            $questions->each(function ($qq) {
                if ($qq->question && $qq->question->options) {
                    $qq->question->options = $qq->question->options->shuffle();
                }
            });
        }

        return [
            'attempt_id'        => $attempt->id,
            'attempt_number'    => $attempt->attempt_number,
            'status'            => $attempt->status->value,
            'started_at'        => $attempt->started_at->toISOString(),
            'time_allowed_sec'  => $attempt->time_allowed_sec,
            'remaining_seconds' => $attempt->remainingSeconds(),
            'total_questions'   => $questions->count(),
            'questions'         => $questions,
            'saved_answers'     => $attempt->answers->keyBy('question_id')->map(fn($a) => [
                'selected_option_ids' => $a->selected_option_ids,
                'text_answer'         => $a->text_answer,
                'fill_blank_answers'  => $a->fill_blank_answers,
                'match_pairs_answer'  => $a->match_pairs_answer,
                'is_bookmarked'       => $a->is_bookmarked,
                'time_spent_sec'      => $a->time_spent_sec,
            ]),
            'quiz' => [
                'title'                     => $quiz->title,
                'type'                      => $quiz->type->value,
                'total_duration_min'        => $quiz->total_duration_min,
                'enable_question_list_view' => $quiz->enable_question_list_view,
                'disable_finish_button'     => $quiz->disable_finish_button,
                'negative_marking'          => $quiz->negative_marking,
            ],
        ];
    }
}