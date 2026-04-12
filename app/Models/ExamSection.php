<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Relations\{BelongsTo, HasMany, MorphToMany};
use Illuminate\Support\Str;

class ExamSection extends Model
{
    use SoftDeletes;

    protected $fillable = [
        'parent_id', 'name', 'slug', 'code', 'type',
        'description', 'short_name', 'icon_url', 'image_url',
        'depth', 'meta', 'sort_order', 'is_active', 'is_featured',
    ];

    protected $casts = [
        'meta'        => 'array',
        'is_active'   => 'boolean',
        'is_featured' => 'boolean',
    ];

    protected static function booted(): void
    {
        static::creating(function (self $s) {
            $s->slug = $s->slug ?: Str::slug($s->name) . '-' . Str::random(4);
            if ($s->parent_id) {
                $parent = static::find($s->parent_id);
                $s->depth = $parent ? $parent->depth + 1 : 0;
            }
        });
    }

    // ── Relationships ──

    public function parent(): BelongsTo
    {
        return $this->belongsTo(self::class, 'parent_id');
    }

    public function children(): HasMany
    {
        return $this->hasMany(self::class, 'parent_id')->orderBy('sort_order');
    }

    public function allChildren(): HasMany
    {
        return $this->children()->with('allChildren');
    }

    public function quizzes(): MorphToMany
    {
        return $this->morphedByMany(Quiz::class, 'linkable', 'exam_section_links')
            ->withPivot('sort_order')->withTimestamps();
    }

    public function practiceSets(): MorphToMany
    {
        return $this->morphedByMany(PracticeSet::class, 'linkable', 'exam_section_links')
            ->withPivot('sort_order')->withTimestamps();
    }

    public function directQuizzes(): HasMany
    {
        return $this->hasMany(Quiz::class, 'exam_section_id');
    }

    // ── Scopes ──

    public function scopeActive(Builder $q): Builder    { return $q->where('is_active', true); }
    public function scopeFeatured(Builder $q): Builder  { return $q->where('is_featured', true); }
    public function scopeRoots(Builder $q): Builder     { return $q->whereNull('parent_id'); }

    public function scopeOfType(Builder $q, string|array $type): Builder
    {
        return $q->whereIn('type', (array) $type);
    }

    public function scopeSearch(Builder $q, string $term): Builder
    {
        return $q->whereRaw('MATCH(name) AGAINST(? IN BOOLEAN MODE)', [$term . '*']);
    }

    // ── Helpers ──

    public function getBreadcrumb(): array
    {
        $path    = [];
        $current = $this->loadMissing('parent');
        while ($current) {
            array_unshift($path, [
                'id'   => $current->id,
                'name' => $current->name,
                'type' => $current->type,
                'slug' => $current->slug,
            ]);
            $current = $current->parent;
        }
        return $path;
    }

    public function getAllDescendantIds(): array
    {
        $ids      = [$this->id];
        $children = self::where('parent_id', $this->id)->get(['id']);
        foreach ($children as $child) {
            $ids = array_merge($ids, self::find($child->id)->getAllDescendantIds());
        }
        return $ids;
    }
}
