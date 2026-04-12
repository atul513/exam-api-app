<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\{BelongsTo, MorphTo};

class ExamSectionLink extends Model
{
    protected $fillable = [
        'exam_section_id', 'linkable_type', 'linkable_id', 'sort_order',
    ];

    public function examSection(): BelongsTo
    {
        return $this->belongsTo(ExamSection::class);
    }

    public function linkable(): MorphTo
    {
        return $this->morphTo();
    }
}
