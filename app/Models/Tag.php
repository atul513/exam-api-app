<?php
namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Str;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;

class Tag extends Model
{
    protected $fillable = ['name', 'slug', 'category'];

    protected static function booted(): void
    {
        static::creating(function (Tag $tag) {
            $tag->slug = $tag->slug ?: Str::slug($tag->name);
        });
    }

    public function questions(): BelongsToMany
    {
        return $this->belongsToMany(Question::class, 'question_tags');
    }
}