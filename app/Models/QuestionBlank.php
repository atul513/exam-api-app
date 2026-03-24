<?php

// ============================================================
// ─── app/Models/QuestionBlank.php ───────────────────────────
// ============================================================

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class QuestionBlank extends Model
{
    protected $fillable = [
        'question_id', 'blank_number', 'correct_answers', 'is_case_sensitive',
    ];

    protected $casts = [
        'correct_answers'   => 'array',
        'is_case_sensitive'  => 'boolean',
    ];

    public function question(): BelongsTo
    {
        return $this->belongsTo(Question::class);
    }

    public function isAnswerCorrect(string $answer): bool
    {
        $answers = $this->correct_answers;

        if (!$this->is_case_sensitive) {
            $answer = strtolower(trim($answer));
            $answers = array_map(fn($a) => strtolower(trim($a)), $answers);
        }

        return in_array($answer, $answers);
    }
}

