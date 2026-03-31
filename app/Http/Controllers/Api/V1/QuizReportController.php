<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Models\QuizAttempt;
use Barryvdh\DomPDF\Facade\Pdf;
use Illuminate\Http\Request;
use Illuminate\Http\JsonResponse;

class QuizReportController extends Controller
{
    /**
     * GET /api/v1/attempts/{attempt}/report
     * Full JSON report (student sees own, teacher/admin sees any).
     */
    public function show(Request $request, QuizAttempt $attempt): JsonResponse
    {
        $this->authorizeAccess($request, $attempt);

        return response()->json(['data' => $this->buildReport($attempt)]);
    }

    /**
     * GET /api/v1/attempts/{attempt}/report/pdf
     * Download PDF report.
     */
    public function downloadPdf(Request $request, QuizAttempt $attempt)
    {
        $this->authorizeAccess($request, $attempt);

        $report = $this->buildReport($attempt);

        $pdf = Pdf::loadView('reports.quiz-attempt', compact('report'))
            ->setPaper('a4', 'portrait')
            ->setOption('defaultFont', 'sans-serif')
            ->setOption('isRemoteEnabled', false);

        $filename = 'quiz-report-' . $attempt->id . '-' . now()->format('Ymd') . '.pdf';

        return $pdf->download($filename);
    }

    // ── HELPERS ───────────────────────────────────────────────────────────

    private function authorizeAccess(Request $request, QuizAttempt $attempt): void
    {
        $user = $request->user();

        $isOwner   = $attempt->user_id === $user->id;
        $isStaff   = in_array($user->role, ['teacher', 'admin', 'superadmin']);

        if (!$isOwner && !$isStaff) {
            abort(403, 'Unauthorized.');
        }

        $completedStatuses = ['completed', 'submitted', 'auto_submitted', 'grading'];
        if (!in_array($attempt->status->value, $completedStatuses)) {
            abort(422, 'Report is only available after the quiz is submitted.');
        }
    }

    private function buildReport(QuizAttempt $attempt): array
    {
        $attempt->load([
            'quiz:id,title,slug,type,pass_percentage,negative_marking,total_marks,total_questions,hide_solutions',
            'quiz.category:id,name',
            'user:id,name,email,avatar',
            'answers' => fn($q) => $q->with([
                'question' => fn($q2) => $q2->with([
                    'options:id,question_id,option_text,is_correct,sort_order',
                    'blanks:id,question_id,blank_number,correct_answers,is_case_sensitive',
                    'matchPairs:id,question_id,column_a_text,column_b_text,sort_order',
                    'expectedAnswer:id,question_id,answer_text,keywords',
                    'subject:id,name',
                    'topic:id,name',
                ])->select('id', 'type', 'difficulty', 'question_text', 'marks', 'negative_marks',
                           'explanation', 'solution_approach', 'subject_id', 'topic_id'),
                'quizQuestion:id,marks_override,negative_marks_override,sort_order',
            ])->orderBy('id'),
        ]);

        $quiz    = $attempt->quiz;
        $answers = $attempt->answers;
        $hideSolutions = $quiz->hide_solutions;

        // ── Summary ──
        $totalTime  = $attempt->time_spent_sec ?? 0;
        $minutes    = intdiv($totalTime, 60);
        $seconds    = $totalTime % 60;

        // ── Per-difficulty breakdown ──
        $byDifficulty = [];
        foreach ($answers as $ans) {
            $diff = $ans->question?->difficulty?->value ?? 'unknown';
            if (!isset($byDifficulty[$diff])) {
                $byDifficulty[$diff] = ['total' => 0, 'correct' => 0, 'incorrect' => 0, 'skipped' => 0, 'marks' => 0];
            }
            $byDifficulty[$diff]['total']++;
            if ($ans->is_correct === true)  $byDifficulty[$diff]['correct']++;
            elseif (!$this->isAnswered($ans)) $byDifficulty[$diff]['skipped']++;
            else                             $byDifficulty[$diff]['incorrect']++;
            $byDifficulty[$diff]['marks'] += (float) ($ans->marks_awarded ?? 0);
        }

        // ── Per-subject breakdown ──
        $bySubject = [];
        foreach ($answers as $ans) {
            $subjectName = $ans->question?->subject?->name ?? 'Unknown';
            if (!isset($bySubject[$subjectName])) {
                $bySubject[$subjectName] = ['total' => 0, 'correct' => 0, 'incorrect' => 0, 'skipped' => 0, 'marks' => 0];
            }
            $bySubject[$subjectName]['total']++;
            if ($ans->is_correct === true)   $bySubject[$subjectName]['correct']++;
            elseif (!$this->isAnswered($ans)) $bySubject[$subjectName]['skipped']++;
            else                             $bySubject[$subjectName]['incorrect']++;
            $bySubject[$subjectName]['marks'] += (float) ($ans->marks_awarded ?? 0);
        }

        // ── Per-question detail ──
        $questionDetails = $answers->map(function ($ans, $index) {
            $q            = $ans->question;
            $effectiveMarks = (float) ($ans->quizQuestion?->marks_override ?? $q?->marks ?? 0);
            $effectiveNeg   = (float) ($ans->quizQuestion?->negative_marks_override ?? $q?->negative_marks ?? 0);

            $status = 'skipped';
            if ($this->isAnswered($ans)) {
                $status = $ans->is_correct ? 'correct' : 'incorrect';
            }

            $detail = [
                'number'          => $index + 1,
                'question_id'     => $q?->id,
                'question_text'   => $q?->question_text,
                'type'            => $q?->type?->value,
                'difficulty'      => $q?->difficulty?->value,
                'subject'         => $q?->subject?->name,
                'topic'           => $q?->topic?->name,
                'max_marks'       => $effectiveMarks,
                'negative_marks'  => $effectiveNeg,
                'marks_awarded'   => (float) ($ans->marks_awarded ?? 0),
                'negative_deducted' => (float) ($ans->negative_marks ?? 0),
                'status'          => $status,
                'time_spent_sec'  => $ans->time_spent_sec,
                'visit_count'     => $ans->visit_count,
                'is_bookmarked'   => $ans->is_bookmarked,
                'student_answer'  => $this->formatStudentAnswer($ans),
            ];

            // Always include correct answer in the report (it's a post-submission document)
            $detail['correct_answer']    = $this->formatCorrectAnswer($q);
            $detail['options']           = $q?->options?->map(fn($o) => [
                'id'         => $o->id,
                'text'       => $o->option_text,
                'is_correct' => $o->is_correct,
            ]);
            $detail['explanation']       = $q?->explanation;
            $detail['solution_approach'] = $q?->solution_approach;

            if ($ans->is_manually_graded) {
                $detail['manually_graded']  = true;
                $detail['grader_feedback']  = $ans->grader_feedback;
            }

            return $detail;
        })->values();

        return [
            'generated_at' => now()->toISOString(),
            'attempt' => [
                'id'             => $attempt->id,
                'attempt_number' => $attempt->attempt_number,
                'status'         => $attempt->status->value,
                'started_at'     => $attempt->started_at?->format('d M Y, h:i A'),
                'submitted_at'   => $attempt->submitted_at?->format('d M Y, h:i A'),
                'time_spent'     => "{$minutes}m {$seconds}s",
                'time_spent_sec' => $totalTime,
                'ip_address'     => $attempt->ip_address,
            ],
            'student' => [
                'id'    => $attempt->user->id,
                'name'  => $attempt->user->name,
                'email' => $attempt->user->email,
            ],
            'quiz' => [
                'id'              => $quiz->id,
                'title'           => $quiz->title,
                'type'            => $quiz->type->value,
                'category'        => $quiz->category?->name,
                'pass_percentage' => $quiz->pass_percentage,
                'total_marks'     => $quiz->total_marks,
                'total_questions' => $quiz->total_questions,
            ],
            'score_summary' => [
                'total_questions'      => (int) $attempt->total_questions,
                'attempted'            => (int) $attempt->attempted_count,
                'correct'              => (int) $attempt->correct_count,
                'incorrect'            => (int) $attempt->incorrect_count,
                'skipped'              => (int) $attempt->skipped_count,
                'total_marks'          => (float) $attempt->total_marks,
                'marks_obtained'       => (float) $attempt->marks_obtained,
                'negative_marks_total' => abs((float) $attempt->negative_marks_total),
                'final_score'          => (float) $attempt->final_score,
                'percentage'           => (float) $attempt->percentage,
                'is_passed'            => (bool)  $attempt->is_passed,
                'rank'                 => $attempt->rank,
                'accuracy'             => $attempt->attempted_count > 0
                    ? round(($attempt->correct_count / $attempt->attempted_count) * 100, 1)
                    : 0,
            ],
            'breakdown_by_difficulty' => $byDifficulty,
            'breakdown_by_subject'    => $bySubject,
            'questions'               => $questionDetails,
        ];
    }

    private function isAnswered($ans): bool
    {
        return !empty($ans->selected_option_ids)
            || !empty($ans->text_answer)
            || !empty($ans->fill_blank_answers)
            || !empty($ans->match_pairs_answer);
    }

    private function formatStudentAnswer($ans): ?string
    {
        // MCQ / multi-select / true-false: resolve option IDs → option texts
        if (!empty($ans->selected_option_ids)) {
            $ids  = (array) $ans->selected_option_ids;
            $texts = $ans->question?->options
                ?->whereIn('id', $ids)
                ->pluck('option_text')
                ->filter()
                ->implode(', ');
            return $texts ?: implode(', ', $ids);
        }

        if (!empty($ans->text_answer)) {
            return $ans->text_answer;
        }

        // Fill-in-the-blank: "Blank 1: answer1 / answer2"
        if (!empty($ans->fill_blank_answers)) {
            $parts = [];
            foreach ((array) $ans->fill_blank_answers as $blankNum => $answer) {
                $parts[] = 'Blank ' . ($blankNum + 1) . ': ' . (is_array($answer) ? implode(' / ', $answer) : $answer);
            }
            return implode("\n", $parts);
        }

        // Match column: "Column A  →  Column B"
        if (!empty($ans->match_pairs_answer)) {
            $pairs = (array) $ans->match_pairs_answer;
            $lines = [];
            foreach ($pairs as $colA => $colB) {
                $lines[] = $colA . '  →  ' . (is_array($colB) ? implode(', ', $colB) : $colB);
            }
            return implode("\n", $lines);
        }

        return null;
    }

    private function formatCorrectAnswer($question): ?string
    {
        if (!$question) return null;

        $type = $question->type?->value;

        // MCQ / Multi-select / True-False: list correct option texts
        if (in_array($type, ['mcq', 'multi_select', 'true_false'])) {
            $correct = $question->options
                ->where('is_correct', true)
                ->pluck('option_text')
                ->filter();
            return $correct->isNotEmpty() ? $correct->implode(', ') : null;
        }

        // Fill-in-the-blank: "Blank 1: answer1 / answer2"
        if ($type === 'fill_blank') {
            $lines = $question->blanks->map(function ($b) {
                $answers = is_array($b->correct_answers)
                    ? implode(' / ', $b->correct_answers)
                    : $b->correct_answers;
                return 'Blank ' . $b->blank_number . ': ' . $answers;
            });
            return $lines->implode("\n");
        }

        // Match column: "Isaac Newton  →  Laws of Motion"
        if ($type === 'match_column') {
            $lines = $question->matchPairs->map(
                fn($m) => $m->column_a_text . '  →  ' . $m->column_b_text
            );
            return $lines->implode("\n");
        }

        // Short / Long answer
        if (in_array($type, ['short_answer', 'long_answer'])) {
            return $question->expectedAnswer?->answer_text;
        }

        return null;
    }
}
