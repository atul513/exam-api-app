<?php

// ============================================================
// ─── app/Models/QuestionMatchPair.php ───────────────────────
// ============================================================

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class QuestionMatchPair extends Model
{
    protected $fillable = [
        'question_id', 'column_a_text', 'column_a_media',
        'column_b_text', 'column_b_media', 'sort_order',
    ];

    protected $casts = [
        'column_a_media' => 'array',
        'column_b_media' => 'array',
    ];

    public function question(): BelongsTo
    {
        return $this->belongsTo(Question::class);
    }
}
