<?php

namespace App\Traits;

use App\Models\ExamSection;
use Illuminate\Database\Eloquent\Relations\{BelongsTo, MorphToMany};

trait HasExamSections
{
    // Primary section (direct FK)
    public function examSection(): BelongsTo
    {
        return $this->belongsTo(ExamSection::class);
    }

    // Multiple sections (polymorphic pivot)
    public function examSections(): MorphToMany
    {
        return $this->morphToMany(ExamSection::class, 'linkable', 'exam_section_links')
            ->withPivot('sort_order')
            ->withTimestamps();
    }

    public function syncExamSections(array $sectionIds): void
    {
        $this->examSections()->sync(
            collect($sectionIds)->mapWithKeys(fn($id, $i) => [$id => ['sort_order' => $i]])
        );
    }
}
