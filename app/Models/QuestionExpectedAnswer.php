<?php

// ============================================================
// ─── app/Models/QuestionExpectedAnswer.php ──────────────────
// ============================================================

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class QuestionExpectedAnswer extends Model
{
    protected $fillable = [
        'question_id', 'answer_text', 'keywords',
        'min_words', 'max_words', 'rubric',
    ];

    protected $casts = [
        'keywords' => 'array',
        'rubric'   => 'array',
    ];

    public function question(): BelongsTo
    {
        return $this->belongsTo(Question::class);
    }
}

