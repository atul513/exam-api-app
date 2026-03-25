<?php
// ─────────────────────────────────────────────────────────────
// FILE: app/Services/QuizService.php
// Admin: create/update quizzes, manage questions & schedules
// ─────────────────────────────────────────────────────────────

namespace App\Services;

use App\Models\{Quiz, QuizQuestion, QuizSection, QuizSchedule, Question};
use App\Enums\QuizStatus;
use Illuminate\Support\Facades\DB;
use Illuminate\Pagination\LengthAwarePaginator;

class QuizService
{
    /**
     * Create quiz with all 4 steps in one transaction.
     */
    public function create(array $data, int $userId): Quiz
    {
        return DB::transaction(function () use ($data, $userId) {

            $quiz = Quiz::create([
                ...$this->extractDetails($data),
                ...$this->extractSettings($data),
                'created_by' => $userId,
            ]);

            // Sections (optional)
            if (!empty($data['sections'])) {
                $this->syncSections($quiz, $data['sections']);
            }

            // Questions
            if (!empty($data['questions'])) {
                $this->syncQuestions($quiz, $data['questions']);
            }

            // Schedules
            if (!empty($data['schedules'])) {
                $this->syncSchedules($quiz, $data['schedules']);
            }

            $quiz->recalculateTotals();

            return $quiz->load(['category', 'sections', 'quizQuestions.question', 'schedules']);
        });
    }

    /**
     * Update quiz.
     */
    public function update(Quiz $quiz, array $data): Quiz
    {
        return DB::transaction(function () use ($quiz, $data) {

            $quiz->update([
                ...$this->extractDetails($data),
                ...$this->extractSettings($data),
            ]);

            if (array_key_exists('sections', $data)) {
                $this->syncSections($quiz, $data['sections'] ?? []);
            }

            if (array_key_exists('questions', $data)) {
                $this->syncQuestions($quiz, $data['questions'] ?? []);
            }

            if (array_key_exists('schedules', $data)) {
                $this->syncSchedules($quiz, $data['schedules'] ?? []);
            }

            $quiz->recalculateTotals();

            return $quiz->fresh()->load(['category', 'sections', 'quizQuestions.question', 'schedules']);
        });
    }

    /**
     * List quizzes with filters.
     */
    public function list(array $filters = []): LengthAwarePaginator
    {
        $query = Quiz::query()
            ->with(['category:id,name,slug', 'creator:id,name'])
            ->withCount(['quizQuestions', 'attempts', 'schedules']);

        if (!empty($filters['type'])) $query->ofType($filters['type']);
        if (!empty($filters['status'])) $query->where('status', $filters['status']);
        if (!empty($filters['visibility'])) $query->where('visibility', $filters['visibility']);
        if (!empty($filters['access_type'])) $query->where('access_type', $filters['access_type']);
        if (!empty($filters['category_id'])) $query->where('category_id', $filters['category_id']);
        if (!empty($filters['search'])) {
            $query->where('title', 'like', '%' . $filters['search'] . '%');
        }

        $sortBy = $filters['sort_by'] ?? 'created_at';
        $query->orderBy($sortBy, $filters['sort_order'] ?? 'desc');

        return $query->paginate($filters['per_page'] ?? 25);
    }

    /**
     * Publish a quiz (validates it has questions).
     */
    public function publish(Quiz $quiz): Quiz
    {
        if ($quiz->total_questions === 0) {
            throw new \Exception('Cannot publish a quiz with no questions.');
        }
        $quiz->update(['status' => QuizStatus::Published]);
        return $quiz;
    }

    // ── SYNC HELPERS ──

    private function syncSections(Quiz $quiz, array $sections): void
    {
        $quiz->sections()->delete();
        foreach ($sections as $i => $s) {
            $quiz->sections()->create([
                'title'              => $s['title'],
                'instructions'       => $s['instructions'] ?? null,
                'sort_order'         => $s['sort_order'] ?? $i,
                'duration_min'       => $s['duration_min'] ?? null,
                'required_questions' => $s['required_questions'] ?? null,
            ]);
        }
    }

    private function syncQuestions(Quiz $quiz, array $questions): void
    {
        $quiz->quizQuestions()->delete();
        foreach ($questions as $i => $q) {
            $quiz->quizQuestions()->create([
                'question_id'             => $q['question_id'],
                'section_id'              => $q['section_id'] ?? null,
                'sort_order'              => $q['sort_order'] ?? $i,
                'marks_override'          => $q['marks_override'] ?? null,
                'negative_marks_override' => $q['negative_marks_override'] ?? null,
            ]);
        }
    }

    private function syncSchedules(Quiz $quiz, array $schedules): void
    {
        $quiz->schedules()->delete();
        foreach ($schedules as $s) {
            $schedule = $quiz->schedules()->create([
                'title'            => $s['title'] ?? null,
                'starts_at'        => $s['starts_at'],
                'ends_at'          => $s['ends_at'],
                'grace_period_min' => $s['grace_period_min'] ?? 0,
                'is_active'        => $s['is_active'] ?? true,
            ]);
            if (!empty($s['user_group_ids'])) {
                $schedule->userGroups()->sync($s['user_group_ids']);
            }
        }
    }

    private function extractDetails(array $data): array
    {
        return collect($data)->only([
            'title', 'slug', 'category_id', 'type', 'access_type',
            'price', 'description', 'thumbnail_url', 'visibility', 'status',
        ])->filter(fn($v) => $v !== null)->toArray();
    }

    private function extractSettings(array $data): array
    {
        return collect($data)->only([
            'duration_mode', 'total_duration_min',
            'marks_mode', 'fixed_marks_per_question',
            'negative_marking', 'negative_marks_per_question',
            'pass_percentage',
            'shuffle_questions', 'shuffle_options', 'max_attempts',
            'disable_finish_button', 'enable_question_list_view',
            'hide_solutions', 'show_leaderboard',
            'show_result_immediately', 'allow_review_after_submit',
            'auto_submit_on_timeout', 'language',
        ])->filter(fn($v) => $v !== null)->toArray();
    }
}