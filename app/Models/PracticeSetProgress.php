<?php
// ─── app/Models/PracticeSetProgress.php ─────────────────────

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class PracticeSetProgress extends Model
{
    protected $table = 'practice_set_progress';

    protected $fillable = [
        'practice_set_id', 'user_id', 'question_id', 'practice_set_question_id',
        'selected_option_ids', 'text_answer', 'fill_blank_answers', 'match_pairs_answer',
        'is_correct', 'points_earned', 'attempts',
    ];

    protected $casts = [
        'selected_option_ids' => 'array',
        'fill_blank_answers'  => 'array',
        'match_pairs_answer'  => 'array',
        'is_correct'          => 'boolean',
    ];

    public function practiceSet(): BelongsTo
    {
        return $this->belongsTo(PracticeSet::class);
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function question(): BelongsTo
    {
        return $this->belongsTo(Question::class);
    }

    public function practiceSetQuestion(): BelongsTo
    {
        return $this->belongsTo(PracticeSetQuestion::class);
    }
}
