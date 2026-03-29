<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Str;

class Plan extends Model
{
    protected $fillable = [
        'name', 'slug', 'description', 'price', 'billing_cycle',
        'duration_days', 'features', 'sort_order', 'is_active', 'is_featured',
    ];

    protected $casts = [
        'features'    => 'array',
        'is_active'   => 'boolean',
        'is_featured' => 'boolean',
        'price'       => 'decimal:2',
    ];

    protected static function booted(): void
    {
        static::creating(function (self $plan) {
            $plan->slug = $plan->slug ?: Str::slug($plan->name);
        });
    }

    // ── Relationships ──

    public function subscriptions(): HasMany
    {
        return $this->hasMany(UserSubscription::class);
    }

    public function activeSubscriptions(): HasMany
    {
        return $this->hasMany(UserSubscription::class)->where('status', 'active');
    }

    public function quizzes(): HasMany
    {
        return $this->hasMany(Quiz::class);
    }

    public function practiceSets(): HasMany
    {
        return $this->hasMany(PracticeSet::class);
    }

    // ── Helpers ──

    public function isLifetime(): bool
    {
        return $this->billing_cycle === 'lifetime';
    }

    public function isFree(): bool
    {
        return $this->price == 0;
    }
}
