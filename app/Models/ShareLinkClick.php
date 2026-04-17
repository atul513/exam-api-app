<?php

// ─── app/Models/ShareLinkClick.php ──────────────────────────

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class ShareLinkClick extends Model
{
    protected $fillable = [
        'share_link_id', 'invitation_id', 'user_id',
        'ip_address', 'user_agent', 'referer', 'source',
    ];

    public function shareLink(): BelongsTo { return $this->belongsTo(ShareLink::class); }
    public function invitation(): BelongsTo { return $this->belongsTo(Invitation::class); }
    public function user(): BelongsTo { return $this->belongsTo(User::class); }
}
