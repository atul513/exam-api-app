<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Support\Str;

class Blog extends Model
{
    use SoftDeletes;

    protected $fillable = [
        'user_id',
        'category_id',
        'title',
        'slug',
        'excerpt',
        'content',
        'featured_image',
        'featured_image_alt',
        'status',
        'published_at',
        'views_count',
        'reading_time',
        'is_featured',
        'allow_comments',
        'meta_title',
        'meta_description',
        'meta_keywords',
        'og_title',
        'og_description',
        'og_image',
        'canonical_url',
        'robots',
        'schema_markup',
    ];

    protected $casts = [
        'published_at'  => 'datetime',
        'is_featured'   => 'boolean',
        'allow_comments'=> 'boolean',
        'views_count'   => 'integer',
        'reading_time'  => 'integer',
        'schema_markup' => 'array',
    ];

    protected static function boot(): void
    {
        parent::boot();

        static::creating(function ($blog) {
            if (empty($blog->slug)) {
                $blog->slug = static::generateUniqueSlug($blog->title);
            }
            if (empty($blog->excerpt) && $blog->content) {
                $blog->excerpt = static::generateExcerpt($blog->content);
            }
            if (empty($blog->reading_time) && $blog->content) {
                $blog->reading_time = static::calculateReadingTime($blog->content);
            }
            if (empty($blog->meta_title)) {
                $blog->meta_title = Str::limit($blog->title, 60);
            }
            if (empty($blog->meta_description) && $blog->excerpt) {
                $blog->meta_description = Str::limit(strip_tags($blog->excerpt), 160);
            }
            if (empty($blog->og_title)) {
                $blog->og_title = $blog->meta_title;
            }
            if (empty($blog->og_description)) {
                $blog->og_description = $blog->meta_description;
            }
        });

        static::updating(function ($blog) {
            if ($blog->isDirty('content')) {
                $blog->reading_time = static::calculateReadingTime($blog->content);
                if (! $blog->isDirty('excerpt')) {
                    $blog->excerpt = static::generateExcerpt($blog->content);
                }
            }
        });
    }

    public static function generateUniqueSlug(string $title, ?int $excludeId = null): string
    {
        $slug = Str::slug($title);
        $originalSlug = $slug;
        $count = 1;

        while (true) {
            $query = static::withTrashed()->where('slug', $slug);
            if ($excludeId) {
                $query->where('id', '!=', $excludeId);
            }
            if (! $query->exists()) {
                break;
            }
            $slug = $originalSlug.'-'.$count++;
        }

        return $slug;
    }

    public static function generateExcerpt(string $content, int $length = 200): string
    {
        return Str::limit(strip_tags($content), $length);
    }

    public static function calculateReadingTime(string $content): int
    {
        $wordCount = str_word_count(strip_tags($content));

        return max(1, (int) ceil($wordCount / 200));
    }

    public function generateSchemaMarkup(): array
    {
        return [
            '@context'        => 'https://schema.org',
            '@type'           => 'Article',
            'headline'        => $this->title,
            'description'     => $this->meta_description,
            'image'           => $this->og_image ?? $this->featured_image,
            'datePublished'   => $this->published_at?->toIso8601String(),
            'dateModified'    => $this->updated_at->toIso8601String(),
            'author'          => [
                '@type' => 'Person',
                'name'  => $this->author?->name,
            ],
            'publisher' => [
                '@type' => 'Organization',
                'name'  => config('app.name'),
            ],
            'mainEntityOfPage' => [
                '@type' => 'WebPage',
                '@id'   => $this->canonical_url ?? url('/blogs/'.$this->slug),
            ],
            'wordCount'    => str_word_count(strip_tags($this->content)),
            'timeRequired' => 'PT'.$this->reading_time.'M',
        ];
    }

    public function incrementViews(): void
    {
        $this->increment('views_count');
    }

    // ─── Relationships ───────────────────────────────────────────────────────

    public function author(): BelongsTo
    {
        return $this->belongsTo(User::class, 'user_id');
    }

    public function category(): BelongsTo
    {
        return $this->belongsTo(BlogCategory::class, 'category_id');
    }

    public function tags(): BelongsToMany
    {
        return $this->belongsToMany(BlogTag::class, 'blog_tag', 'blog_id', 'tag_id');
    }

    public function comments(): HasMany
    {
        return $this->hasMany(BlogComment::class);
    }

    public function approvedComments(): HasMany
    {
        return $this->hasMany(BlogComment::class)
            ->where('status', 'approved')
            ->whereNull('parent_id');
    }

    // ─── Scopes ──────────────────────────────────────────────────────────────

    public function scopePublished($query)
    {
        return $query->where('status', 'published')
            ->where('published_at', '<=', now());
    }

    public function scopeFeatured($query)
    {
        return $query->where('is_featured', true);
    }

    public function scopeByCategory($query, string $slug)
    {
        return $query->whereHas('category', fn ($q) => $q->where('slug', $slug));
    }

    public function scopeByTag($query, string $slug)
    {
        return $query->whereHas('tags', fn ($q) => $q->where('blog_tags.slug', $slug));
    }

    public function scopeSearch($query, string $term)
    {
        return $query->where(function ($q) use ($term) {
            $q->where('title', 'like', "%{$term}%")
                ->orWhere('excerpt', 'like', "%{$term}%")
                ->orWhere('content', 'like', "%{$term}%");
        });
    }
}