<?php

// ============================================================
// ─── app/Models/QuizSection.php ─────────────────────────────
// ============================================================

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\{BelongsTo, HasMany};

class QuizSection extends Model
{
    protected $fillable = [
        'quiz_id', 'title', 'instructions', 'sort_order',
        'duration_min', 'required_questions',
    ];

    public function quiz(): BelongsTo { return $this->belongsTo(Quiz::class); }

    public function quizQuestions(): HasMany
    {
        return $this->hasMany(QuizQuestion::class, 'section_id')->orderBy('sort_order');
    }
}
