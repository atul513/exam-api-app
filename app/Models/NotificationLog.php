<?php

// ─── app/Models/NotificationLog.php ─────────────────────────

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class NotificationLog extends Model
{
    protected $fillable = [
        'invitation_id', 'channel', 'recipient', 'subject', 'body',
        'status', 'error_message', 'external_id', 'sent_at', 'delivered_at',
    ];

    protected $casts = [
        'sent_at'      => 'datetime',
        'delivered_at' => 'datetime',
    ];

    public function invitation(): BelongsTo { return $this->belongsTo(Invitation::class); }
}

