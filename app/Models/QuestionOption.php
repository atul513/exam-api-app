<?php

// ============================================================
// ─── app/Models/QuestionOption.php ──────────────────────────
// ============================================================

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class QuestionOption extends Model
{
    protected $fillable = [
        'question_id', 'option_text', 'option_media',
        'is_correct', 'sort_order', 'explanation',
    ];

    protected $casts = [
        'is_correct'   => 'boolean',
        'option_media'  => 'array',
    ];

    public function question(): BelongsTo
    {
        return $this->belongsTo(Question::class);
    }
}

