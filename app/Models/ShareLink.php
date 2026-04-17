<?php

// ============================================================
// MODELS
// ============================================================


// ─── app/Models/ShareLink.php ───────────────────────────────

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\{BelongsTo, HasMany, MorphTo};
use Illuminate\Support\Str;

class ShareLink extends Model
{
    protected $fillable = [
        'share_code', 'shareable_type', 'shareable_id',
        'title', 'message', 'thumbnail_url',
        'is_active', 'require_registration', 'expires_at', 'max_registrations',
        'click_count', 'registration_count', 'attempt_count',
        'created_by',
    ];

    protected $casts = [
        'is_active'            => 'boolean',
        'require_registration' => 'boolean',
        'expires_at'           => 'datetime',
    ];

    protected static function booted(): void
    {
        static::creating(fn(self $s) => $s->share_code = $s->share_code ?: Str::random(16));
    }

    public function shareable(): MorphTo { return $this->morphTo(); }
    public function creator(): BelongsTo { return $this->belongsTo(User::class, 'created_by'); }
    public function clicks(): HasMany { return $this->hasMany(ShareLinkClick::class); }

    public function isValid(): bool
    {
        if (!$this->is_active) return false;
        if ($this->expires_at && $this->expires_at->isPast()) return false;
        if ($this->max_registrations && $this->registration_count >= $this->max_registrations) return false;
        return true;
    }

    public function getFullUrl(): string
    {
        return config('app.frontend_url', config('app.url')) . '/share/' . $this->share_code;
    }
}
