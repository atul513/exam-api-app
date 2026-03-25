<?php


// ============================================================
// ─── app/Models/QuizQuestion.php (Pivot model) ─────────────
// ============================================================

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class QuizQuestion extends Model
{
    protected $fillable = [
        'quiz_id', 'question_id', 'section_id', 'sort_order',
        'marks_override', 'negative_marks_override',
    ];

    protected $casts = [
        'marks_override'          => 'decimal:2',
        'negative_marks_override' => 'decimal:2',
    ];

    public function quiz(): BelongsTo { return $this->belongsTo(Quiz::class); }
    public function question(): BelongsTo { return $this->belongsTo(Question::class); }
    public function section(): BelongsTo { return $this->belongsTo(QuizSection::class, 'section_id'); }

    /**
     * Get effective marks (override or from question bank).
     */
    public function getEffectiveMarks(): float
    {
        if ($this->marks_override !== null) {
            return (float) $this->marks_override;
        }
        if ($this->quiz && $this->quiz->marks_mode === 'fixed') {
            return (float) ($this->quiz->fixed_marks_per_question ?? 1);
        }
        return (float) ($this->question->marks ?? 1);
    }

    public function getEffectiveNegativeMarks(): float
    {
        if ($this->negative_marks_override !== null) {
            return (float) $this->negative_marks_override;
        }
        if ($this->quiz && $this->quiz->negative_marking) {
            return (float) ($this->quiz->negative_marks_per_question ?? $this->question->negative_marks ?? 0);
        }
        return 0;
    }
}
