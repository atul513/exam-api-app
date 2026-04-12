<?php

namespace App\Services;

use App\Models\{ExamSection, Quiz, PracticeSet};
use Illuminate\Pagination\LengthAwarePaginator;

class ExamSectionService
{
    public function create(array $data): ExamSection
    {
        return ExamSection::create($data);
    }

    public function update(ExamSection $section, array $data): ExamSection
    {
        $section->update($data);
        return $section->fresh();
    }

    public function list(array $filters = []): LengthAwarePaginator|array
    {
        $query = ExamSection::query();

        if (!empty($filters['type']))       $query->ofType($filters['type']);
        if (!empty($filters['parent_id']))  $query->where('parent_id', $filters['parent_id']);
        if (!empty($filters['search']))     $query->search($filters['search']);
        if (isset($filters['roots']) && $filters['roots']) $query->roots();
        if (isset($filters['is_active']))   $query->where('is_active', $filters['is_active']);
        if (isset($filters['is_featured'])) $query->where('is_featured', $filters['is_featured']);

        $query->orderBy('sort_order')->orderBy('name');

        if (!empty($filters['format']) && $filters['format'] === 'tree') {
            return $query->roots()->with(['allChildren' => fn($q) => $q->orderBy('sort_order')])
                ->get()
                ->toArray();
        }

        return $query->paginate($filters['per_page'] ?? 50);
    }

    public function getTree(ExamSection $section): ExamSection
    {
        return $section->load(['allChildren' => fn($q) => $q->orderBy('sort_order')]);
    }

    public function getContent(ExamSection $section, array $filters = []): array
    {
        $ids = $section->getAllDescendantIds();

        $quizzes = Quiz::where(function ($q) use ($ids) {
                $q->whereIn('exam_section_id', $ids)
                  ->orWhereHas('examSections', fn($q2) => $q2->whereIn('exam_sections.id', $ids));
            })
            ->when(!empty($filters['status']), fn($q) => $q->where('status', $filters['status']))
            ->with(['category:id,name', 'examSection:id,name,type'])
            ->latest()->limit(50)->get();

        $practiceSets = PracticeSet::where(function ($q) use ($ids) {
                $q->whereIn('exam_section_id', $ids)
                  ->orWhereHas('examSections', fn($q2) => $q2->whereIn('exam_sections.id', $ids));
            })
            ->when(!empty($filters['status']), fn($q) => $q->where('status', $filters['status']))
            ->with(['subject:id,name', 'examSection:id,name,type'])
            ->latest()->limit(50)->get();

        return [
            'quizzes'       => $quizzes,
            'practice_sets' => $practiceSets,
            'total_count'   => $quizzes->count() + $practiceSets->count(),
        ];
    }

    public static function availableTypes(): array
    {
        return [
            'exam_group'   => 'Exam Group (Competitive, Academic, State)',
            'exam'         => 'Exam (JEE, NEET, SSC, GATE)',
            'exam_variant' => 'Exam Variant (JEE Mains, JEE Advanced)',
            'board'        => 'Education Board (CBSE, ICSE)',
            'state_board'  => 'State Board (Maharashtra, UP)',
            'class'        => 'Class / Grade (Class 10, Class 12)',
            'semester'     => 'Semester (Sem 1, Sem 2)',
            'year'         => 'Year (2024, 2025)',
            'subject'      => 'Subject (Physics, Chemistry)',
            'chapter'      => 'Chapter (Newton\'s Laws)',
            'topic'        => 'Topic (sub-chapter level)',
            'unit'         => 'Unit (grouping of chapters)',
            'custom'       => 'Custom (admin-defined)',
        ];
    }
}
