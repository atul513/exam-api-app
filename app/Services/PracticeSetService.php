<?php
// ─────────────────────────────────────────────────────────────
// FILE: app/Services/PracticeSetService.php
// ─────────────────────────────────────────────────────────────

namespace App\Services;

use App\Models\{PracticeSet, PracticeSetQuestion, PracticeSetProgress, UserRewardPoint, Question};
use Illuminate\Support\Facades\DB;
use Illuminate\Pagination\LengthAwarePaginator;

class PracticeSetService
{
    /**
     * Create practice set with questions.
     */
    public function create(array $data, int $userId): PracticeSet
    {
        return DB::transaction(function () use ($data, $userId) {

            $set = PracticeSet::create([
                'title'                => $data['title'],
                'category_id'          => $data['category_id'] ?? null,
                'subject_id'           => $data['subject_id'] ?? null,
                'topic_id'             => $data['topic_id'] ?? null,
                'description'          => $data['description'] ?? null,
                'thumbnail_url'        => $data['thumbnail_url'] ?? null,
                'access_type'          => $data['access_type'] ?? 'free',
                'price'                => $data['price'] ?? null,
                'status'               => $data['status'] ?? 'draft',
                'allow_reward_points'  => $data['allow_reward_points'] ?? false,
                'points_mode'          => $data['points_mode'] ?? 'auto',
                'points_per_question'  => $data['points_per_question'] ?? null,
                'show_reward_popup'    => $data['show_reward_popup'] ?? false,
                'created_by'           => $userId,
            ]);

            if (!empty($data['questions'])) {
                $this->syncQuestions($set, $data['questions']);
            }

            $set->recalculateTotals();

            return $set->load(['category', 'subject:id,name', 'topic:id,name', 'practiceSetQuestions.question']);
        });
    }

    /**
     * Update practice set.
     */
    public function update(PracticeSet $set, array $data): PracticeSet
    {
        return DB::transaction(function () use ($set, $data) {

            $set->update(collect($data)->only([
                'title', 'category_id', 'subject_id', 'topic_id',
                'description', 'thumbnail_url', 'access_type', 'price', 'status',
                'allow_reward_points', 'points_mode', 'points_per_question',
                'show_reward_popup',
            ])->filter(fn($v) => $v !== null)->toArray());

            if (array_key_exists('questions', $data)) {
                $this->syncQuestions($set, $data['questions'] ?? []);
            }

            $set->recalculateTotals();

            return $set->fresh()->load(['category', 'subject:id,name', 'topic:id,name', 'practiceSetQuestions.question']);
        });
    }

    /**
     * List practice sets with filters.
     */
    public function list(array $filters = []): LengthAwarePaginator
    {
        $query = PracticeSet::query()
            ->with(['category:id,name', 'subject:id,name', 'topic:id,name', 'creator:id,name']);

        if (!empty($filters['status']))      $query->where('status', $filters['status']);
        if (!empty($filters['access_type'])) $query->where('access_type', $filters['access_type']);
        if (!empty($filters['category_id'])) $query->where('category_id', $filters['category_id']);
        if (!empty($filters['subject_id']))  $query->forSubject($filters['subject_id']);
        if (!empty($filters['topic_id']))    $query->forTopic($filters['topic_id']);
        if (!empty($filters['search']))      $query->where('title', 'like', '%' . $filters['search'] . '%');

        $query->orderBy($filters['sort_by'] ?? 'created_at', $filters['sort_order'] ?? 'desc');

        return $query->paginate($filters['per_page'] ?? 25);
    }

    /**
     * Sync questions to the practice set.
     */
    private function syncQuestions(PracticeSet $set, array $questions): void
    {
        $set->practiceSetQuestions()->delete();
        foreach ($questions as $i => $q) {
            $set->practiceSetQuestions()->create([
                'question_id'    => $q['question_id'],
                'sort_order'     => $q['sort_order'] ?? $i,
                'points_override' => $q['points_override'] ?? null,
            ]);
        }
    }

    /**
     * Grade a single answer in practice mode (instant feedback).
     * Returns: is_correct, points_earned, correct_answer, explanation
     */
    public function checkAnswer(PracticeSet $set, int $userId, array $data): array
    {
        $psq = $set->practiceSetQuestions()
            ->where('question_id', $data['question_id'])
            ->with('question.options', 'question.blanks', 'question.matchPairs', 'question.expectedAnswer')
            ->firstOrFail();

        $question = $psq->question;

        // Grade the answer
        $isCorrect = $this->gradeAnswer($question, $data);

        // Calculate points
        $pointsEarned = 0;
        if ($isCorrect && $set->allow_reward_points) {
            $pointsEarned = $set->getPointsForQuestion($psq);
        }

        // Save/update progress
        $progress = PracticeSetProgress::updateOrCreate(
            [
                'practice_set_id' => $set->id,
                'user_id'         => $userId,
                'question_id'     => $question->id,
            ],
            [
                'practice_set_question_id' => $psq->id,
                'selected_option_ids'      => $data['selected_option_ids'] ?? null,
                'text_answer'              => $data['text_answer'] ?? null,
                'fill_blank_answers'       => $data['fill_blank_answers'] ?? null,
                'match_pairs_answer'       => $data['match_pairs_answer'] ?? null,
                'is_correct'               => $isCorrect,
                'points_earned'            => $pointsEarned,
            ]
        );

        // Increment attempt count
        $progress->increment('attempts');

        // Award reward points (only on first correct answer)
        if ($isCorrect && $pointsEarned > 0 && $progress->attempts === 1) {
            UserRewardPoint::create([
                'user_id'     => $userId,
                'source_type' => 'practice_set',
                'source_id'   => $set->id,
                'question_id' => $question->id,
                'points'      => $pointsEarned,
                'description' => "Correct answer in '{$set->title}'",
            ]);
        }

        // Build response with explanation
        $response = [
            'is_correct'    => $isCorrect,
            'points_earned' => $pointsEarned,
            'attempts'      => $progress->attempts,
            'show_popup'    => $isCorrect && $set->show_reward_popup && $pointsEarned > 0,
        ];

        // Include correct answer and explanation
        $response['correct_answer'] = $this->getCorrectAnswer($question);
        $response['explanation'] = $question->explanation;
        $response['solution_approach'] = $question->solution_approach;

        return $response;
    }

    /**
     * Grade an answer against the question.
     */
    private function gradeAnswer(Question $question, array $data): bool
    {
        switch ($question->type->value) {
            case 'mcq':
            case 'true_false':
                $correctIds = $question->options->where('is_correct', true)->pluck('id')->toArray();
                $selected = $data['selected_option_ids'] ?? [];
                return count($selected) === 1 && in_array($selected[0], $correctIds);

            case 'multi_select':
                $correctIds = $question->options->where('is_correct', true)->pluck('id')->sort()->values()->toArray();
                $selected = collect($data['selected_option_ids'] ?? [])->sort()->values()->toArray();
                return $selected === $correctIds;

            case 'short_answer':
                $expected = $question->expectedAnswer;
                if (!$expected) return false;
                $userAns = strtolower(trim($data['text_answer'] ?? ''));
                $accepted = array_map('strtolower', $expected->keywords ?? []);
                $accepted[] = strtolower(trim($expected->answer_text));
                return in_array($userAns, $accepted);

            case 'fill_blank':
                $blanks = $question->blanks;
                $userAnswers = $data['fill_blank_answers'] ?? [];
                foreach ($blanks as $blank) {
                    $userAns = $userAnswers[(string) $blank->blank_number] ?? '';
                    if (!$blank->isAnswerCorrect($userAns)) return false;
                }
                return true;

            case 'match_column':
                $pairs = $question->matchPairs;
                $userPairs = $data['match_pairs_answer'] ?? [];
                foreach ($pairs as $pair) {
                    if (($userPairs[(string) $pair->id] ?? null) != $pair->id) return false;
                }
                return true;

            case 'long_answer':
                return false; // can't auto-grade

            default:
                return false;
        }
    }

    /**
     * Get the correct answer for display after answering.
     */
    private function getCorrectAnswer(Question $question): mixed
    {
        switch ($question->type->value) {
            case 'mcq':
            case 'true_false':
            case 'multi_select':
                return $question->options
                    ->where('is_correct', true)
                    ->map(fn($o) => ['id' => $o->id, 'option_text' => $o->option_text])
                    ->values();

            case 'short_answer':
                return $question->expectedAnswer?->answer_text;

            case 'fill_blank':
                return $question->blanks->mapWithKeys(fn($b) => [
                    $b->blank_number => $b->correct_answers
                ]);

            case 'match_column':
                return $question->matchPairs->map(fn($p) => [
                    'column_a' => $p->column_a_text,
                    'column_b' => $p->column_b_text,
                ]);

            case 'long_answer':
                return $question->expectedAnswer?->answer_text;

            default:
                return null;
        }
    }
}
