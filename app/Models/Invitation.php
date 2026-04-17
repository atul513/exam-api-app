<?php

// ─── app/Models/Invitation.php ──────────────────────────────

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\{BelongsTo, HasMany, MorphTo};
use Illuminate\Support\Str;

class Invitation extends Model
{
    protected $fillable = [
        'invite_code', 'invitable_type', 'invitable_id',
        'recipient_name', 'recipient_email', 'recipient_phone',
        'recipient_user_id', 'message', 'sent_via', 'status',
        'sent_at', 'opened_at', 'registered_at', 'attempted_at', 'completed_at',
        'expires_at', 'invited_by',
    ];

    protected $casts = [
        'sent_via'      => 'array',
        'sent_at'       => 'datetime',
        'opened_at'     => 'datetime',
        'registered_at' => 'datetime',
        'attempted_at'  => 'datetime',
        'completed_at'  => 'datetime',
        'expires_at'    => 'datetime',
    ];

    protected static function booted(): void
    {
        static::creating(fn(self $i) => $i->invite_code = $i->invite_code ?: Str::random(20));
    }

    public function invitable(): MorphTo { return $this->morphTo(); }
    public function inviter(): BelongsTo { return $this->belongsTo(User::class, 'invited_by'); }
    public function recipientUser(): BelongsTo { return $this->belongsTo(User::class, 'recipient_user_id'); }
    public function clicks(): HasMany { return $this->hasMany(ShareLinkClick::class, 'invitation_id'); }
    public function notificationLogs(): HasMany { return $this->hasMany(NotificationLog::class); }

    public function isValid(): bool
    {
        if (in_array($this->status, ['expired', 'cancelled'])) return false;
        if ($this->expires_at && $this->expires_at->isPast()) return false;
        return true;
    }

    public function getFullUrl(): string
    {
        return config('app.frontend_url', config('app.url')) . '/invite/' . $this->invite_code;
    }

    public function markOpened(): void
    {
        if (!$this->opened_at) {
            $this->update(['status' => 'opened', 'opened_at' => now()]);
        }
    }

    public function markRegistered(int $userId): void
    {
        $this->update([
            'status'            => 'registered',
            'recipient_user_id' => $userId,
            'registered_at'     => now(),
        ]);
    }

    public function markAttempted(): void
    {
        if (!$this->attempted_at) {
            $this->update(['status' => 'attempted', 'attempted_at' => now()]);
        }
    }

    public function markCompleted(): void
    {
        $this->update(['status' => 'completed', 'completed_at' => now()]);
    }
}
