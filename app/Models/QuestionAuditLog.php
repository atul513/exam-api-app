<?php

// ============================================================
// ─── app/Models/QuestionAuditLog.php ────────────────────────
// ============================================================

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class QuestionAuditLog extends Model
{
    protected $fillable = [
        'question_id', 'action', 'changed_fields', 'performed_by',
    ];

    protected $casts = [
        'changed_fields' => 'array',
    ];

    public function question(): BelongsTo
    {
        return $this->belongsTo(Question::class);
    }

    public function performer(): BelongsTo
    {
        return $this->belongsTo(User::class, 'performed_by');
    }
}
