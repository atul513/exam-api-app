<?php

// ============================================================
// ─── app/Models/QuizAttemptAnswer.php ───────────────────────
// ============================================================

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class QuizAttemptAnswer extends Model
{
    protected $fillable = [
        'attempt_id', 'question_id', 'quiz_question_id',
        'selected_option_ids', 'text_answer', 'fill_blank_answers', 'match_pairs_answer',
        'is_correct', 'marks_awarded', 'negative_marks',
        'is_manually_graded', 'graded_by', 'grader_feedback',
        'time_spent_sec', 'is_bookmarked', 'visit_count',
    ];

    protected $casts = [
        'selected_option_ids' => 'array',
        'fill_blank_answers'  => 'array',
        'match_pairs_answer'  => 'array',
        'is_correct'          => 'boolean',
        'is_manually_graded'  => 'boolean',
        'is_bookmarked'       => 'boolean',
        'marks_awarded'       => 'decimal:2',
        'negative_marks'      => 'decimal:2',
    ];

    public function attempt(): BelongsTo { return $this->belongsTo(QuizAttempt::class, 'attempt_id'); }
    public function question(): BelongsTo { return $this->belongsTo(Question::class); }
    public function quizQuestion(): BelongsTo { return $this->belongsTo(QuizQuestion::class); }
    public function grader(): BelongsTo { return $this->belongsTo(User::class, 'graded_by'); }

    public function isAnswered(): bool
    {
        return !empty($this->selected_option_ids)
            || !empty($this->text_answer)
            || !empty($this->fill_blank_answers)
            || !empty($this->match_pairs_answer);
    }
}
