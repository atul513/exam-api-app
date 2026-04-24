<?php

namespace App\Services;

use App\Models\{ImportBatch, Question, Subject, Topic};
use App\Enums\ImportStatus;
use Illuminate\Support\Facades\{DB, Storage, Log};

class JsonQuestionImportService
{
    private const TYPE_MAP = [
        'mcq_single'    => 'mcq',
        'mcq'           => 'mcq',
        'mcq_multiple'  => 'multi_select',
        'multi_select'  => 'multi_select',
        'true_false'    => 'true_false',
        'short_answer'  => 'short_answer',
        'long_answer'   => 'long_answer',
        'fill_blank'    => 'fill_blank',
        'match_column'  => 'match_column',
    ];

    public function __construct(private QuestionService $questionService) {}

    public function process(ImportBatch $batch, int $subjectId, int $topicId, int $userId): ImportBatch
    {
        $batch->update([
            'status'     => ImportStatus::Processing,
            'started_at' => now(),
        ]);

        try {
            $filePath = Storage::disk('local')->path($batch->file_path);
            $contents = @file_get_contents($filePath);

            if ($contents === false) {
                throw new \RuntimeException('Could not read uploaded file.');
            }

            $items = json_decode($contents, true);

            if (!is_array($items) || json_last_error() !== JSON_ERROR_NONE) {
                throw new \RuntimeException('Invalid JSON: ' . json_last_error_msg());
            }

            // Accept { "questions": [...] } wrapper or a raw array
            if (isset($items['questions']) && is_array($items['questions'])) {
                $items = $items['questions'];
            }

            if (!array_is_list($items)) {
                throw new \RuntimeException('JSON must be an array of question objects.');
            }

            $subject = Subject::findOrFail($subjectId);
            $topic   = Topic::where('id', $topicId)->where('subject_id', $subjectId)->firstOrFail();

            $totalRows = count($items);
            $batch->update(['total_rows' => $totalRows]);

            $errors  = [];
            $success = 0;
            $processed = 0;

            foreach ($items as $i => $item) {
                $processed++;
                $rowNum = $i + 1;

                $validation = $this->validateItem($item, $rowNum);
                if (!empty($validation)) {
                    $errors = array_merge($errors, $validation);
                    continue;
                }

                try {
                    $data = $this->transformItem($item, $subject, $topic, $batch->id);
                    $this->questionService->create($data, $userId);
                    $success++;
                } catch (\Throwable $e) {
                    $errors[] = [
                        'row'         => $rowNum,
                        'external_id' => $item['external_id'] ?? null,
                        'field'       => null,
                        'error'       => $e->getMessage(),
                        'severity'    => 'error',
                    ];
                    Log::warning('JSON import row failed', [
                        'batch_id' => $batch->id,
                        'row'      => $rowNum,
                        'error'    => $e->getMessage(),
                    ]);
                }
            }

            $batch->update([
                'processed_rows' => $processed,
                'success_count'  => $success,
                'error_count'    => count($errors),
                'error_log'      => $errors,
                'summary'        => [
                    'total_rows' => $totalRows,
                    'imported'   => $success,
                    'errors'     => count($errors),
                    'subject_id' => $subjectId,
                    'topic_id'   => $topicId,
                ],
                'completed_at'   => now(),
            ]);

            $batch->markCompleted();
        } catch (\Throwable $e) {
            $batch->update([
                'status'       => ImportStatus::Failed,
                'error_log'    => [[
                    'row'      => 0,
                    'error'    => $e->getMessage(),
                    'severity' => 'fatal',
                ]],
                'completed_at' => now(),
            ]);
            Log::error('JSON import failed', [
                'batch_id' => $batch->id,
                'error'    => $e->getMessage(),
            ]);
        }

        return $batch->fresh();
    }

    private function validateItem(array $item, int $rowNum): array
    {
        $errors = [];
        $extId  = $item['external_id'] ?? null;

        if (empty($item['question_text'])) {
            $errors[] = $this->err($rowNum, $extId, 'question_text', 'Question text is required.');
        }

        $type = $this->normalizeType($item['type'] ?? null);
        if (!$type) {
            $errors[] = $this->err($rowNum, $extId, 'type', 'Unknown or missing type: ' . ($item['type'] ?? 'null'));
            return $errors;
        }

        if (in_array($type, ['mcq', 'multi_select', 'true_false'])) {
            $options = $this->collectOptions($item);
            if (count($options) < 2) {
                $errors[] = $this->err($rowNum, $extId, 'options', 'At least 2 options required.');
            }

            $correct = $this->parseCorrect($item['correct_answer'] ?? null);
            if (empty($correct)) {
                $errors[] = $this->err($rowNum, $extId, 'correct_answer', 'correct_answer is required.');
            } elseif ($type === 'mcq' && count($correct) !== 1) {
                $errors[] = $this->err($rowNum, $extId, 'correct_answer', 'MCQ (single) requires exactly 1 correct answer.');
            } elseif ($type === 'multi_select' && count($correct) < 2) {
                $errors[] = $this->err($rowNum, $extId, 'correct_answer', 'multi_select requires at least 2 correct answers.');
            }
        }

        if (in_array($type, ['short_answer', 'long_answer']) && empty($item['correct_answer']) && empty($item['expected_answer'])) {
            $errors[] = $this->err($rowNum, $extId, 'correct_answer', "{$type} requires a correct_answer or expected_answer.");
        }

        return $errors;
    }

    private function transformItem(array $item, Subject $subject, Topic $topic, int $batchId): array
    {
        $type = $this->normalizeType($item['type']);

        $data = [
            'subject_id'        => $subject->id,
            'topic_id'          => $topic->id,
            'type'              => $type,
            'difficulty'        => $this->normalizeDifficulty($item['difficulty'] ?? null),
            'status'            => 'draft',
            'question_text'     => trim($item['question_text']),
            'marks'             => $this->num($item['marks'] ?? null, 1),
            'negative_marks'    => $this->num($item['negative_marks'] ?? null, 0),
            'time_limit_sec'    => isset($item['time_limit_sec']) ? (int) $item['time_limit_sec'] : null,
            'explanation'       => $item['explanation'] ?? null,
            'solution_approach' => $item['solution_approach'] ?? null,
            'language'          => $item['language'] ?? 'en',
            'source'            => $item['source'] ?? null,
            'external_id'       => $item['external_id'] ?? null,
            'import_batch_id'   => $batchId,
        ];

        if (!empty($item['image_url'])) {
            $data['question_media'] = ['image_url' => $item['image_url']];
        }

        if (in_array($type, ['mcq', 'multi_select', 'true_false'])) {
            $correct = $this->parseCorrect($item['correct_answer']);
            $data['options'] = [];
            foreach ($this->collectOptions($item) as $letter => $text) {
                $data['options'][] = [
                    'option_text' => $text,
                    'is_correct'  => in_array($letter, $correct),
                    'sort_order'  => ord($letter) - ord('a'),
                ];
            }
        }

        if (in_array($type, ['short_answer', 'long_answer'])) {
            $data['expected_answer'] = [
                'answer_text' => $item['correct_answer'] ?? ($item['expected_answer']['answer_text'] ?? ''),
                'keywords'    => $item['expected_answer']['keywords'] ?? [],
                'min_words'   => $item['expected_answer']['min_words'] ?? null,
                'max_words'   => $item['expected_answer']['max_words'] ?? null,
            ];
        }

        if (!empty($item['tags']) && is_array($item['tags'])) {
            $data['tags'] = array_values(array_filter(array_map('trim', $item['tags'])));
        }

        return $data;
    }

    private function normalizeType(?string $type): ?string
    {
        if (!$type) return null;
        $key = strtolower(trim($type));
        return self::TYPE_MAP[$key] ?? null;
    }

    private function normalizeDifficulty(?string $difficulty): string
    {
        $d = strtolower(trim((string) $difficulty));
        return in_array($d, ['easy', 'medium', 'hard', 'expert']) ? $d : 'medium';
    }

    private function collectOptions(array $item): array
    {
        $options = [];
        foreach (['a', 'b', 'c', 'd', 'e'] as $letter) {
            $val = $item["option_{$letter}"] ?? null;
            if ($val !== null && trim((string) $val) !== '') {
                $options[$letter] = trim((string) $val);
            }
        }
        return $options;
    }

    private function parseCorrect($value): array
    {
        if (empty($value)) return [];
        if (is_array($value)) {
            return array_map(fn($v) => strtolower(trim((string) $v)), $value);
        }
        return array_values(array_filter(array_map(
            fn($v) => strtolower(trim($v)),
            preg_split('/[,|;\s]+/', (string) $value)
        )));
    }

    private function num($value, float $default = 0): float
    {
        if ($value === null || $value === '') return $default;
        return is_numeric($value) ? (float) $value : $default;
    }

    private function err(int $row, ?string $extId, ?string $field, string $msg): array
    {
        return [
            'row'         => $row,
            'external_id' => $extId,
            'field'       => $field,
            'error'       => $msg,
            'severity'    => 'error',
        ];
    }
}
