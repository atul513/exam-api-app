<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Database\Eloquent\Relations\{BelongsTo, HasMany};

class Topic extends Model
{
    use SoftDeletes;

    protected $fillable = [
        'subject_id', 'parent_topic_id', 'name', 'code',
        'depth', 'sort_order', 'is_active',
    ];

    protected $casts = [
        'is_active' => 'boolean',
    ];

    public function subject(): BelongsTo
    {
        return $this->belongsTo(Subject::class);
    }

    public function parent(): BelongsTo
    {
        return $this->belongsTo(Topic::class, 'parent_topic_id');
    }

    public function children(): HasMany
    {
        return $this->hasMany(Topic::class, 'parent_topic_id')->orderBy('sort_order');
    }

    // Recursive children (full tree)
    public function allChildren(): HasMany
    {
        return $this->children()->with('allChildren');
    }

    public function questions(): HasMany
    {
        return $this->hasMany(Question::class);
    }

    public function scopeActive($query)
    {
        return $query->where('is_active', true);
    }
}

