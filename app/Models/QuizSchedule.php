<?php

// ============================================================
// ─── app/Models/QuizSchedule.php ────────────────────────────
// ============================================================

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\{BelongsTo, BelongsToMany};

class QuizSchedule extends Model
{
    protected $fillable = [
        'quiz_id', 'title', 'starts_at', 'ends_at',
        'grace_period_min', 'is_active',
    ];

    protected $casts = [
        'starts_at'  => 'datetime',
        'ends_at'    => 'datetime',
        'is_active'  => 'boolean',
    ];

    public function quiz(): BelongsTo { return $this->belongsTo(Quiz::class); }

    public function userGroups(): BelongsToMany
    {
        return $this->belongsToMany(UserGroup::class, 'quiz_schedule_groups', 'schedule_id', 'user_group_id');
    }

    public function isActive(): bool
    {
        return $this->is_active
            && $this->starts_at->lte(now())
            && $this->ends_at->addMinutes($this->grace_period_min)->gte(now());
    }

    public function scopeActive($q)
    {
        return $q->where('is_active', true)
            ->where('starts_at', '<=', now())
            ->where('ends_at', '>=', now());
    }
}
