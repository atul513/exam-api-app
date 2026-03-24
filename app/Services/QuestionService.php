<?php

// ============================================================
// PHASE 3: SERVICE LAYER — Business Logic
// ============================================================


// ─── app/Services/QuestionService.php ───────────────────────

namespace App\Services;

use App\Models\{Question, QuestionOption, QuestionBlank, QuestionMatchPair, QuestionExpectedAnswer, QuestionAuditLog, Tag};
use App\Enums\{QuestionType, QuestionStatus};
use Illuminate\Support\Facades\DB;
use Illuminate\Pagination\LengthAwarePaginator;

class QuestionService
{
    /**
     * Create a question with all type-specific child records in a single transaction.
     */
    public function create(array $data, int $userId): Question
    {
        return DB::transaction(function () use ($data, $userId) {

            // 1. Create the question
            $question = Question::create([
                'subject_id'       => $data['subject_id'],
                'topic_id'         => $data['topic_id'],
                'type'             => $data['type'],
                'difficulty'       => $data['difficulty'] ?? 'medium',
                'status'           => QuestionStatus::Draft,
                'question_text'    => $data['question_text'],
                'question_media'   => $data['question_media'] ?? [],
                'marks'            => $data['marks'],
                'negative_marks'   => $data['negative_marks'] ?? 0,
                'time_limit_sec'   => $data['time_limit_sec'] ?? null,
                'explanation'      => $data['explanation'] ?? null,
                'explanation_media' => $data['explanation_media'] ?? [],
                'solution_approach' => $data['solution_approach'] ?? null,
                'language'         => $data['language'] ?? 'en',
                'source'           => $data['source'] ?? null,
                'created_by'       => $userId,
            ]);

            // 2. Create type-specific child records
            $type = QuestionType::from($data['type']);

            if ($type->hasOptions() && !empty($data['options'])) {
                $this->createOptions($question, $data['options']);
            }

            if ($type->hasBlanks() && !empty($data['blanks'])) {
                $this->createBlanks($question, $data['blanks']);
            }

            if ($type->hasMatchPairs() && !empty($data['match_pairs'])) {
                $this->createMatchPairs($question, $data['match_pairs']);
            }

            if ($type->hasExpectedAnswer() && !empty($data['expected_answer'])) {
                $this->createExpectedAnswer($question, $data['expected_answer']);
            }

            // 3. Attach tags
            if (!empty($data['tags'])) {
                $this->syncTags($question, $data['tags']);
            }

            // 4. Audit log
            $this->logAudit($question, 'created', $userId);

            return $question->load($this->getRelationsForType($type));
        });
    }

    /**
     * Update a question and its child records.
     */
    public function update(Question $question, array $data, int $userId): Question
    {
        return DB::transaction(function () use ($question, $data, $userId) {

            $original = $question->getOriginal();

            // 1. Update main question fields
            $question->update(collect($data)->only([
                'subject_id', 'topic_id', 'difficulty',
                'question_text', 'question_media',
                'marks', 'negative_marks', 'time_limit_sec',
                'explanation', 'explanation_media', 'solution_approach',
                'language', 'source',
            ])->toArray());

            // 2. Replace child records (delete + re-create for simplicity)
            $type = $question->type;

            if ($type->hasOptions() && isset($data['options'])) {
                $question->options()->delete();
                $this->createOptions($question, $data['options']);
            }

            if ($type->hasBlanks() && isset($data['blanks'])) {
                $question->blanks()->delete();
                $this->createBlanks($question, $data['blanks']);
            }

            if ($type->hasMatchPairs() && isset($data['match_pairs'])) {
                $question->matchPairs()->delete();
                $this->createMatchPairs($question, $data['match_pairs']);
            }

            if ($type->hasExpectedAnswer() && isset($data['expected_answer'])) {
                $question->expectedAnswer()->delete();
                $this->createExpectedAnswer($question, $data['expected_answer']);
            }

            // 3. Sync tags
            if (isset($data['tags'])) {
                $this->syncTags($question, $data['tags']);
            }

            // 4. Audit log with changed fields
            $changes = $this->getChangedFields($original, $question->fresh()->toArray());
            if (!empty($changes)) {
                $this->logAudit($question, 'updated', $userId, $changes);
            }

            return $question->fresh()->load($this->getRelationsForType($type));
        });
    }

    /**
     * List questions with advanced filtering + pagination.
     */
    public function list(array $filters = []): LengthAwarePaginator
    {
        $query = Question::query()
            ->with(['subject:id,name,code', 'topic:id,name,code', 'tags:id,name,slug']);

        // Apply filters
        if (!empty($filters['subject_id'])) {
            $query->forSubject($filters['subject_id']);
        }
        if (!empty($filters['topic_id'])) {
            $query->forTopic($filters['topic_id']);
        }
        if (!empty($filters['type'])) {
            $query->ofType(explode(',', $filters['type']));
        }
        if (!empty($filters['difficulty'])) {
            $query->ofDifficulty(explode(',', $filters['difficulty']));
        }
        if (!empty($filters['status'])) {
            $query->ofStatus(explode(',', $filters['status']));
        }
        if (!empty($filters['tags'])) {
            $query->withTags(explode(',', $filters['tags']));
        }
        if (!empty($filters['search'])) {
            $query->search($filters['search']);
        }
        if (!empty($filters['language'])) {
            $query->where('language', $filters['language']);
        }
        if (!empty($filters['created_by'])) {
            $query->where('created_by', $filters['created_by']);
        }
        if (!empty($filters['created_after'])) {
            $query->where('created_at', '>=', $filters['created_after']);
        }
        if (!empty($filters['created_before'])) {
            $query->where('created_at', '<=', $filters['created_before']);
        }
        if (!empty($filters['import_batch_id'])) {
            $query->where('import_batch_id', $filters['import_batch_id']);
        }

        // Sorting
        $sortBy = $filters['sort_by'] ?? 'created_at';
        $sortOrder = $filters['sort_order'] ?? 'desc';
        $allowedSorts = ['created_at', 'marks', 'difficulty', 'times_used', 'updated_at'];

        if (in_array($sortBy, $allowedSorts)) {
            $query->orderBy($sortBy, $sortOrder);
        }

        $perPage = min((int) ($filters['per_page'] ?? 25), 100);

        return $query->paginate($perPage);
    }

    /**
     * Get a single question with all relations loaded.
     */
    public function show(Question $question): Question
    {
        return $question->loadTypeSpecificRelations()
            ->load([
                'subject:id,name,code',
                'topic:id,name,code',
                'tags:id,name,slug',
                'creator:id,name',
                'reviewer:id,name',
                'auditLogs' => fn($q) => $q->with('performer:id,name')->latest()->limit(20),
            ]);
    }

    /**
     * Get aggregated counts for filter sidebar.
     */
    public function getAggregations(array $baseFilters = []): array
    {
        // Start with same base filters but get counts
        $base = Question::query();

        if (!empty($baseFilters['subject_id'])) {
            $base->forSubject($baseFilters['subject_id']);
        }

        return [
            'by_type' => (clone $base)->selectRaw('type, COUNT(*) as count')
                ->groupBy('type')->pluck('count', 'type'),

            'by_difficulty' => (clone $base)->selectRaw('difficulty, COUNT(*) as count')
                ->groupBy('difficulty')->pluck('count', 'difficulty'),

            'by_status' => (clone $base)->selectRaw('status, COUNT(*) as count')
                ->groupBy('status')->pluck('count', 'status'),

            'total' => (clone $base)->count(),
        ];
    }

    /**
     * Change question status (approve/reject/archive).
     */
    public function changeStatus(Question $question, QuestionStatus $status, int $userId, ?string $reason = null): Question
    {
        $oldStatus = $question->status;
        $question->update([
            'status'      => $status,
            'reviewed_by' => in_array($status, [QuestionStatus::Approved, QuestionStatus::Rejected])
                ? $userId : $question->reviewed_by,
        ]);

        $this->logAudit($question, $status->value, $userId, [
            'status' => ['old' => $oldStatus->value, 'new' => $status->value],
            'reason' => $reason,
        ]);

        return $question;
    }

    /**
     * Bulk status update.
     */
    public function bulkChangeStatus(array $ids, QuestionStatus $status, int $userId): int
    {
        $count = Question::whereIn('id', $ids)->update(['status' => $status]);

        // Log each
        foreach ($ids as $id) {
            QuestionAuditLog::create([
                'question_id'   => $id,
                'action'        => 'bulk_' . $status->value,
                'performed_by'  => $userId,
            ]);
        }

        return $count;
    }

    /**
     * Clone/duplicate a question.
     */
    public function clone(Question $question, int $userId): Question
    {
        $question->loadTypeSpecificRelations()->load('tags');

        $newData = $question->toArray();
        $newData['status'] = 'draft';
        $newData['external_id'] = null;
        $newData['import_batch_id'] = null;
        $newData['question_text'] = '[COPY] ' . $newData['question_text'];

        // Map tags to slugs
        $newData['tags'] = $question->tags->pluck('slug')->toArray();

        // Map child records
        if ($question->type->hasOptions()) {
            $newData['options'] = $question->options->map(fn($o) => $o->only([
                'option_text', 'option_media', 'is_correct', 'sort_order', 'explanation'
            ]))->toArray();
        }
        if ($question->type->hasBlanks()) {
            $newData['blanks'] = $question->blanks->map(fn($b) => $b->only([
                'blank_number', 'correct_answers', 'is_case_sensitive'
            ]))->toArray();
        }
        if ($question->type->hasMatchPairs()) {
            $newData['match_pairs'] = $question->matchPairs->map(fn($m) => $m->only([
                'column_a_text', 'column_a_media', 'column_b_text', 'column_b_media', 'sort_order'
            ]))->toArray();
        }
        if ($question->type->hasExpectedAnswer() && $question->expectedAnswer) {
            $newData['expected_answer'] = $question->expectedAnswer->only([
                'answer_text', 'keywords', 'min_words', 'max_words', 'rubric'
            ]);
        }

        return $this->create($newData, $userId);
    }

    /**
     * Dashboard statistics.
     */
    public function getStats(): array
    {
        return [
            'total'          => Question::count(),
            'by_subject'     => Question::join('subjects', 'subjects.id', '=', 'questions.subject_id')
                                  ->selectRaw('subjects.name, COUNT(*) as count')
                                  ->groupBy('subjects.name')
                                  ->pluck('count', 'name'),
            'by_type'        => Question::selectRaw('type, COUNT(*) as count')
                                  ->groupBy('type')->pluck('count', 'type'),
            'by_difficulty'  => Question::selectRaw('difficulty, COUNT(*) as count')
                                  ->groupBy('difficulty')->pluck('count', 'difficulty'),
            'by_status'      => Question::selectRaw('status, COUNT(*) as count')
                                  ->groupBy('status')->pluck('count', 'status'),
            'this_month'     => Question::where('created_at', '>=', now()->startOfMonth())->count(),
            'recent_imports' => \App\Models\ImportBatch::latest()->limit(5)->get(),
        ];
    }

    // ── PRIVATE HELPERS ──

    private function createOptions(Question $question, array $options): void
    {
        foreach ($options as $i => $opt) {
            $question->options()->create([
                'option_text'  => $opt['option_text'] ?? $opt['text'] ?? '',
                'option_media' => $opt['option_media'] ?? [],
                'is_correct'   => $opt['is_correct'] ?? false,
                'sort_order'   => $opt['sort_order'] ?? $i,
                'explanation'  => $opt['explanation'] ?? null,
            ]);
        }
    }

    private function createBlanks(Question $question, array $blanks): void
    {
        foreach ($blanks as $blank) {
            $question->blanks()->create([
                'blank_number'      => $blank['blank_number'],
                'correct_answers'   => $blank['correct_answers'],
                'is_case_sensitive'  => $blank['is_case_sensitive'] ?? false,
            ]);
        }
    }

    private function createMatchPairs(Question $question, array $pairs): void
    {
        foreach ($pairs as $i => $pair) {
            $question->matchPairs()->create([
                'column_a_text'  => $pair['column_a_text'],
                'column_a_media' => $pair['column_a_media'] ?? [],
                'column_b_text'  => $pair['column_b_text'],
                'column_b_media' => $pair['column_b_media'] ?? [],
                'sort_order'     => $pair['sort_order'] ?? $i,
            ]);
        }
    }

    private function createExpectedAnswer(Question $question, array $answer): void
    {
        $question->expectedAnswer()->create([
            'answer_text' => $answer['answer_text'],
            'keywords'    => $answer['keywords'] ?? [],
            'min_words'   => $answer['min_words'] ?? null,
            'max_words'   => $answer['max_words'] ?? null,
            'rubric'      => $answer['rubric'] ?? null,
        ]);
    }

    private function syncTags(Question $question, array $tagSlugs): void
    {
        $tagIds = collect($tagSlugs)->map(function ($slug) {
            return Tag::firstOrCreate(
                ['slug' => $slug],
                ['name' => str_replace('-', ' ', $slug), 'slug' => $slug]
            )->id;
        });

        $question->tags()->sync($tagIds);
    }

    private function logAudit(Question $question, string $action, int $userId, ?array $changes = null): void
    {
        QuestionAuditLog::create([
            'question_id'   => $question->id,
            'action'        => $action,
            'changed_fields' => $changes,
            'performed_by'  => $userId,
        ]);
    }

    private function getChangedFields(array $original, array $current): array
    {
        $changes = [];
        $trackFields = ['question_text', 'marks', 'difficulty', 'status', 'explanation', 'topic_id'];

        foreach ($trackFields as $field) {
            if (($original[$field] ?? null) !== ($current[$field] ?? null)) {
                $changes[$field] = [
                    'old' => $original[$field] ?? null,
                    'new' => $current[$field] ?? null,
                ];
            }
        }

        return $changes;
    }

    private function getRelationsForType(QuestionType $type): array
    {
        $relations = ['subject:id,name,code', 'topic:id,name,code', 'tags:id,name,slug'];

        if ($type->hasOptions()) $relations[] = 'options';
        if ($type->hasBlanks()) $relations[] = 'blanks';
        if ($type->hasMatchPairs()) $relations[] = 'matchPairs';
        if ($type->hasExpectedAnswer()) $relations[] = 'expectedAnswer';

        return $relations;
    }
}