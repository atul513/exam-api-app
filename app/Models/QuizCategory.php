<?php
// ============================================================
// ─── app/Models/QuizCategory.php ────────────────────────────
// ============================================================

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Database\Eloquent\Relations\{HasMany, BelongsTo};
use Illuminate\Support\Str;

class QuizCategory extends Model
{
    use SoftDeletes;

    protected $fillable = [
        'parent_id', 'name', 'slug', 'description',
        'icon_url', 'sort_order', 'is_active',
    ];

    protected $casts = ['is_active' => 'boolean'];

    protected static function booted(): void
    {
        static::creating(fn(self $c) => $c->slug = $c->slug ?: Str::slug($c->name));
    }

    public function parent(): BelongsTo
    {
        return $this->belongsTo(self::class, 'parent_id');
    }

    public function children(): HasMany
    {
        return $this->hasMany(self::class, 'parent_id')->orderBy('sort_order');
    }

    public function quizzes(): HasMany
    {
        return $this->hasMany(Quiz::class, 'category_id');
    }

    public function scopeActive($q) { return $q->where('is_active', true); }
}
