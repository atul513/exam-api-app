<?php
// ─────────────────────────────────────────────────────────────
// FILE: app/Services/GradingService.php
// Auto-grades objective questions, calculates scores
// ─────────────────────────────────────────────────────────────

namespace App\Services;

use App\Models\{Quiz, QuizAttempt, QuizAttemptAnswer, QuizQuestion, QuizLeaderboard};
use App\Models\{Question, QuestionOption, QuestionBlank, QuestionMatchPair};
use App\Enums\AttemptStatus;
use Illuminate\Support\Facades\DB;

class GradingService
{
    /**
     * Grade an entire attempt after submission.
     */
    public function gradeAttempt(QuizAttempt $attempt): QuizAttempt
    {
        $attempt->load([
            'answers.question' => fn($q) => $q->with(['options', 'blanks', 'matchPairs', 'expectedAnswer']),
            'answers.quizQuestion' => fn($q) => $q->with(['quiz', 'question']),
            'quiz',
        ]);

        $hasSubjective = false;

        foreach ($attempt->answers as $answer) {
            if (!$answer->isAnswered()) {
                $answer->update(['is_correct' => null, 'marks_awarded' => 0]);
                continue;
            }

            $question = $answer->question;

            // Auto-grade objective types
            if (in_array($question->type->value, ['mcq', 'multi_select', 'true_false', 'fill_blank', 'match_column'])) {
                $this->gradeObjective($answer, $question, $answer->quizQuestion);
            } elseif ($question->type->value === 'short_answer') {
                $this->gradeShortAnswer($answer, $question, $answer->quizQuestion);
            } else {
                // long_answer → needs manual grading
                $hasSubjective = true;
            }
        }

        // Calculate totals
        $this->calculateScores($attempt);

        $attempt->update([
            'status' => $hasSubjective ? AttemptStatus::Grading : AttemptStatus::Completed,
        ]);

        if (!$hasSubjective) {
            $this->updateLeaderboard($attempt);
        }

        // Update question bank stats
        $this->updateQuestionStats($attempt);

        return $attempt->fresh();
    }

    /**
     * Grade a single objective answer.
     */
    private function gradeObjective(QuizAttemptAnswer $answer, Question $question, QuizQuestion $qq): void
    {
        $isCorrect = false;
        $marks = 0;
        $negative = 0;

        switch ($question->type->value) {
            case 'mcq':
            case 'true_false':
                $correctIds = $question->options->where('is_correct', true)->pluck('id')->toArray();
                $selected = $answer->selected_option_ids ?? [];
                $isCorrect = count($selected) === 1 && in_array($selected[0], $correctIds);
                break;

            case 'multi_select':
                $correctIds = $question->options->where('is_correct', true)->pluck('id')->sort()->values()->toArray();
                $selected = collect($answer->selected_option_ids ?? [])->sort()->values()->toArray();
                $isCorrect = $selected === $correctIds;
                break;

            case 'fill_blank':
                $blanks = $question->blanks;
                $userAnswers = $answer->fill_blank_answers ?? [];
                $allCorrect = true;
                foreach ($blanks as $blank) {
                    $userAns = $userAnswers[(string) $blank->blank_number] ?? '';
                    if (!$blank->isAnswerCorrect($userAns)) {
                        $allCorrect = false;
                        break;
                    }
                }
                $isCorrect = $allCorrect;
                break;

            case 'match_column':
                $pairs = $question->matchPairs;
                $userPairs = $answer->match_pairs_answer ?? [];
                $allCorrect = true;
                foreach ($pairs as $pair) {
                    $userMatch = $userPairs[(string) $pair->id] ?? null;
                    if ((int) $userMatch !== $pair->id) {
                        // Check if user matched column_a to correct column_b
                        $allCorrect = false;
                        break;
                    }
                }
                $isCorrect = $allCorrect;
                break;
        }

        if ($isCorrect) {
            $marks = $qq->getEffectiveMarks();
        } else {
            $negative = $qq->getEffectiveNegativeMarks();
        }

        $answer->update([
            'is_correct'     => $isCorrect,
            'marks_awarded'  => $marks,
            'negative_marks' => $negative,
        ]);
    }

    /**
     * Grade short answer using keyword matching.
     */
    private function gradeShortAnswer(QuizAttemptAnswer $answer, Question $question, QuizQuestion $qq): void
    {
        $expected = $question->expectedAnswer;
        if (!$expected) return;

        $userAnswer = strtolower(trim($answer->text_answer ?? ''));
        $acceptedAnswers = array_map('strtolower', $expected->keywords ?? []);
        $acceptedAnswers[] = strtolower(trim($expected->answer_text));

        $isCorrect = in_array($userAnswer, $acceptedAnswers);

        $answer->update([
            'is_correct'     => $isCorrect,
            'marks_awarded'  => $isCorrect ? $qq->getEffectiveMarks() : 0,
            'negative_marks' => $isCorrect ? 0 : $qq->getEffectiveNegativeMarks(),
        ]);
    }

    /**
     * Calculate total scores for an attempt.
     */
    private function calculateScores(QuizAttempt $attempt): void
    {
        $answers = $attempt->answers()->get();

        $marksObtained = $answers->sum('marks_awarded');
        $negativeMks = $answers->sum('negative_marks');
        $finalScore = $marksObtained - $negativeMks;
        $totalMarks = $attempt->quiz->total_marks ?: 1;
        $percentage = round(($finalScore / $totalMarks) * 100, 2);

        $attempt->update([
            'total_marks'         => $totalMarks,
            'marks_obtained'      => $marksObtained,
            'negative_marks_total' => $negativeMks,
            'final_score'         => max(0, $finalScore),
            'percentage'          => max(0, $percentage),
            'is_passed'           => $percentage >= $attempt->quiz->pass_percentage,
            'total_questions'     => $answers->count(),
            'attempted_count'     => $answers->filter(fn($a) => $a->isAnswered())->count(),
            'correct_count'       => $answers->where('is_correct', true)->count(),
            'incorrect_count'     => $answers->where('is_correct', false)->count(),
            'skipped_count'       => $answers->filter(fn($a) => !$a->isAnswered())->count(),
            'submitted_at'        => $attempt->submitted_at ?? now(),
            'time_spent_sec'      => max(0, (int) $attempt->started_at->diffInSeconds(now())),
        ]);
    }

    /**
     * Update leaderboard with best attempt per user.
     */
    private function updateLeaderboard(QuizAttempt $attempt): void
    {
        QuizLeaderboard::updateOrCreate(
            ['quiz_id' => $attempt->quiz_id, 'user_id' => $attempt->user_id],
            [
                'attempt_id'     => $attempt->id,
                'final_score'    => $attempt->final_score,
                'percentage'     => $attempt->percentage,
                'time_spent_sec' => $attempt->time_spent_sec,
                'correct_count'  => $attempt->correct_count,
            ]
        );

        // Recalculate ranks: higher score wins, on tie faster time wins
        $entries = QuizLeaderboard::where('quiz_id', $attempt->quiz_id)
            ->orderByDesc('final_score')
            ->orderBy('time_spent_sec')
            ->get();

        foreach ($entries as $i => $entry) {
            $entry->update(['rank' => $i + 1]);
        }

        // Update rank on attempt too
        $myEntry = $entries->firstWhere('user_id', $attempt->user_id);
        if ($myEntry) {
            $attempt->update(['rank' => $myEntry->rank]);
        }
    }

    /**
     * Update question bank usage stats.
     */
    private function updateQuestionStats(QuizAttempt $attempt): void
    {
        foreach ($attempt->answers as $answer) {
            $question = $answer->question;
            $question->increment('times_used');
            if ($answer->is_correct === true) $question->increment('times_correct');
            if ($answer->is_correct === false) $question->increment('times_incorrect');
        }
    }
}