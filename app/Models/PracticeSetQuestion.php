<?php
// ─── app/Models/PracticeSetQuestion.php ─────────────────────

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class PracticeSetQuestion extends Model
{
    protected $fillable = [
        'practice_set_id', 'question_id', 'sort_order', 'points_override',
    ];

    public function practiceSet(): BelongsTo
    {
        return $this->belongsTo(PracticeSet::class);
    }

    public function question(): BelongsTo
    {
        return $this->belongsTo(Question::class);
    }
}
