<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class UserSubscription extends Model
{
    protected $fillable = [
        'user_id', 'plan_id', 'status',
        'starts_at', 'expires_at',
        'payment_reference', 'payment_method', 'amount_paid', 'notes',
    ];

    protected $casts = [
        'starts_at'  => 'datetime',
        'expires_at' => 'datetime',
        'amount_paid' => 'decimal:2',
    ];

    // ── Relationships ──

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function plan(): BelongsTo
    {
        return $this->belongsTo(Plan::class);
    }

    // ── Helpers ──

    public function isActive(): bool
    {
        if ($this->status !== 'active') return false;
        if ($this->expires_at === null) return true;   // lifetime
        return $this->expires_at->isFuture();
    }

    public function daysRemaining(): ?int
    {
        if (!$this->isActive()) return 0;
        if ($this->expires_at === null) return null;   // lifetime → null
        return max(0, (int) now()->diffInDays($this->expires_at, false));
    }
}
