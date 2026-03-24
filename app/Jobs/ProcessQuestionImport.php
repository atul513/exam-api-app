<?php

// ============================================================
// ─── app/Jobs/ProcessQuestionImport.php ─────────────────────
// ============================================================

namespace App\Jobs;

use App\Models\{ImportBatch, Question, Subject, Topic, Tag};
use App\Services\QuestionService;
use App\Enums\ImportStatus;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\{InteractsWithQueue, SerializesModels};
use Illuminate\Support\Facades\{DB, Log, Storage};
use PhpOffice\PhpSpreadsheet\IOFactory;

class ProcessQuestionImport implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public int $timeout = 600;      // 10 minutes max
    public int $tries = 1;          // don't retry, errors are logged

    public function __construct(
        private ImportBatch $batch,
        private bool $dryRun = false,
    ) {}

    public function handle(QuestionService $questionService): void
    {
        $this->batch->update([
            'status'     => ImportStatus::Processing,
            'started_at' => now(),
        ]);

        try {
            $filePath = Storage::disk('local')->path($this->batch->file_path);
            $spreadsheet = IOFactory::load($filePath);

            // ── Parse main questions sheet ──
            $mainSheet = $spreadsheet->getSheetByName('questions')
                ?? $spreadsheet->getSheet(0);

            $rows = $this->sheetToArray($mainSheet);
            $totalRows = count($rows);
            $this->batch->update(['total_rows' => $totalRows]);

            // ── Parse supplementary sheets ──
            $fillBlanks = $this->parseSupplementarySheet($spreadsheet, 'fill_blanks');
            $matchPairs = $this->parseSupplementarySheet($spreadsheet, 'match_pairs');
            $longAnswers = $this->parseSupplementarySheet($spreadsheet, 'long_answers');

            // ── Cache lookups (avoid N+1 queries) ──
            $subjects = Subject::pluck('id', 'code')->toArray();
            $topics = Topic::pluck('id', 'code')->toArray();

            $errors = [];
            $successCount = 0;
            $batchData = [];

            foreach ($rows as $index => $row) {
                $rowNum = $index + 2; // +2 for 1-indexed + header

                try {
                    // Validate row
                    $validationErrors = $this->validateRow($row, $rowNum, $subjects, $topics);
                    if (!empty($validationErrors)) {
                        $errors = array_merge($errors, $validationErrors);
                        continue;
                    }

                    // Transform row to question data
                    $questionData = $this->transformRow(
                        $row, $subjects, $topics,
                        $fillBlanks, $matchPairs, $longAnswers
                    );

                    if (!$this->dryRun) {
                        // Insert in mini-batches of 50
                        $batchData[] = [
                            'data'    => $questionData,
                            'row_num' => $rowNum,
                        ];

                        if (count($batchData) >= 50) {
                            $successCount += $this->insertBatch($batchData, $questionService);
                            $batchData = [];
                        }
                    } else {
                        $successCount++;
                    }

                } catch (\Throwable $e) {
                    $errors[] = [
                        'row'         => $rowNum,
                        'external_id' => $row['external_id'] ?? null,
                        'field'       => 'general',
                        'error'       => $e->getMessage(),
                        'severity'    => 'error',
                    ];
                }

                // Update progress every 50 rows
                if ($index % 50 === 0) {
                    $this->batch->update([
                        'processed_rows' => $index + 1,
                        'success_count'  => $successCount,
                        'error_count'    => count($errors),
                    ]);
                }
            }

            // Insert remaining batch
            if (!empty($batchData) && !$this->dryRun) {
                $successCount += $this->insertBatch($batchData, $questionService);
            }

            // Finalize
            $this->batch->update([
                'processed_rows' => $totalRows,
                'success_count'  => $successCount,
                'error_count'    => count($errors),
                'error_log'      => $errors,
                'status'         => count($errors) > 0
                    ? ImportStatus::Partial
                    : ImportStatus::Completed,
                'completed_at'   => now(),
            ]);

        } catch (\Throwable $e) {
            Log::error('Import failed', [
                'batch_id' => $this->batch->id,
                'error'    => $e->getMessage(),
            ]);

            $this->batch->update([
                'status'       => ImportStatus::Failed,
                'error_log'    => [['row' => 0, 'error' => $e->getMessage(), 'severity' => 'fatal']],
                'completed_at' => now(),
            ]);
        }
    }

    // ── Transform Excel row → question create data ──

    private function transformRow(
        array $row, array $subjects, array $topics,
        array $fillBlanks, array $matchPairs, array $longAnswers
    ): array {
        $type = strtolower(trim($row['type']));
        $extId = $row['external_id'];

        $data = [
            'subject_id'       => $subjects[$row['subject_code']],
            'topic_id'         => $topics[$row['topic_code']],
            'type'             => $type,
            'difficulty'       => strtolower(trim($row['difficulty'] ?? 'medium')),
            'question_text'    => trim($row['question_text']),
            'marks'            => (float) $row['marks'],
            'negative_marks'   => (float) ($row['negative_marks'] ?? 0),
            'time_limit_sec'   => !empty($row['time_limit_sec']) ? (int) $row['time_limit_sec'] : null,
            'explanation'      => $row['explanation'] ?? null,
            'solution_approach' => $row['solution_approach'] ?? null,
            'language'         => $row['language'] ?? 'en',
            'source'           => $row['source'] ?? null,
            'external_id'      => $extId,
            'import_batch_id'  => $this->batch->id,
        ];

        // Tags
        if (!empty($row['tags'])) {
            $data['tags'] = array_map('trim', explode(',', $row['tags']));
        }

        // Options (MCQ, Multi-select, True/False)
        if (in_array($type, ['mcq', 'multi_select', 'true_false'])) {
            $correctAnswers = array_map('trim', explode(',', strtoupper($row['correct_answer'] ?? '')));
            $optionLetters = ['A', 'B', 'C', 'D', 'E'];
            $options = [];

            foreach ($optionLetters as $i => $letter) {
                $key = 'option_' . strtolower($letter);
                if (!empty($row[$key])) {
                    $options[] = [
                        'option_text' => trim($row[$key]),
                        'is_correct'  => in_array($letter, $correctAnswers),
                        'sort_order'  => $i,
                    ];
                }
            }
            $data['options'] = $options;
        }

        // Fill blanks
        if ($type === 'fill_blank' && isset($fillBlanks[$extId])) {
            $data['blanks'] = collect($fillBlanks[$extId])->map(fn($b) => [
                'blank_number'     => (int) $b['blank_number'],
                'correct_answers'  => array_map('trim', explode('|', $b['correct_answers'])),
                'is_case_sensitive' => strtolower($b['is_case_sensitive'] ?? 'false') === 'true',
            ])->toArray();
        }

        // Match pairs
        if ($type === 'match_column' && isset($matchPairs[$extId])) {
            $data['match_pairs'] = collect($matchPairs[$extId])->map(fn($m, $i) => [
                'column_a_text' => trim($m['column_a']),
                'column_b_text' => trim($m['column_b']),
                'sort_order'    => (int) ($m['sort_order'] ?? $i),
            ])->toArray();
        }

        // Short/Long answer
        if (in_array($type, ['short_answer', 'long_answer'])) {
            $answerText = $row['correct_answer'] ?? '';
            $keywords = [];

            if ($type === 'long_answer' && isset($longAnswers[$extId])) {
                $la = $longAnswers[$extId][0]; // take first
                $answerText = $la['model_answer'] ?? $answerText;
                $keywords = !empty($la['keywords'])
                    ? array_map('trim', explode(',', $la['keywords']))
                    : [];
            }

            $data['expected_answer'] = [
                'answer_text' => $answerText,
                'keywords'    => $keywords,
                'min_words'   => !empty($longAnswers[$extId][0]['min_words'])
                    ? (int) $longAnswers[$extId][0]['min_words'] : null,
                'max_words'   => !empty($longAnswers[$extId][0]['max_words'])
                    ? (int) $longAnswers[$extId][0]['max_words'] : null,
            ];
        }

        return $data;
    }

    // ── Validate a single row ──

    private function validateRow(array $row, int $rowNum, array $subjects, array $topics): array
    {
        $errors = [];
        $extId = $row['external_id'] ?? "row_{$rowNum}";

        $addError = function (string $field, string $msg) use (&$errors, $rowNum, $extId) {
            $errors[] = [
                'row' => $rowNum, 'external_id' => $extId,
                'field' => $field, 'error' => $msg, 'severity' => 'error',
            ];
        };

        // Required fields
        if (empty($row['external_id'])) $addError('external_id', 'External ID is required.');
        if (empty($row['type'])) $addError('type', 'Question type is required.');
        if (empty($row['question_text'])) $addError('question_text', 'Question text is required.');
        if (empty($row['marks'])) $addError('marks', 'Marks is required.');

        // Type check
        $validTypes = ['mcq', 'multi_select', 'true_false', 'short_answer', 'long_answer', 'fill_blank', 'match_column'];
        $type = strtolower(trim($row['type'] ?? ''));
        if (!in_array($type, $validTypes)) {
            $addError('type', "Invalid type: {$type}. Must be one of: " . implode(', ', $validTypes));
        }

        // Subject/topic exist
        if (!empty($row['subject_code']) && !isset($subjects[$row['subject_code']])) {
            $addError('subject_code', "Subject '{$row['subject_code']}' not found.");
        }
        if (!empty($row['topic_code']) && !isset($topics[$row['topic_code']])) {
            $addError('topic_code', "Topic '{$row['topic_code']}' not found.");
        }

        // MCQ: must have options and correct answer
        if ($type === 'mcq') {
            if (empty($row['option_a']) || empty($row['option_b'])) {
                $addError('options', 'MCQ requires at least 2 options (A, B).');
            }
            $correct = array_filter(explode(',', strtoupper($row['correct_answer'] ?? '')));
            if (count($correct) !== 1) {
                $addError('correct_answer', 'MCQ must have exactly 1 correct answer.');
            }
        }

        // Multi-select: at least 2 correct
        if ($type === 'multi_select') {
            $correct = array_filter(explode(',', strtoupper($row['correct_answer'] ?? '')));
            if (count($correct) < 2) {
                $addError('correct_answer', 'Multi-select must have at least 2 correct answers.');
            }
        }

        // True/false
        if ($type === 'true_false') {
            $answer = strtoupper(trim($row['correct_answer'] ?? ''));
            if (!in_array($answer, ['TRUE', 'FALSE'])) {
                $addError('correct_answer', 'True/False answer must be TRUE or FALSE.');
            }
        }

        return $errors;
    }

    // ── Insert a batch of questions ──

    private function insertBatch(array $batchData, QuestionService $service): int
    {
        $success = 0;
        DB::beginTransaction();

        try {
            foreach ($batchData as $item) {
                $service->create($item['data'], $this->batch->imported_by);
                $success++;
            }
            DB::commit();
        } catch (\Throwable $e) {
            DB::rollBack();
            Log::error("Batch insert failed at row {$item['row_num']}: " . $e->getMessage());
        }

        return $success;
    }

    // ── Parse sheet into associative array ──

    private function sheetToArray($sheet): array
    {
        $rows = $sheet->toArray(null, true, true, true);
        if (empty($rows)) return [];

        $headers = array_map(fn($h) => strtolower(trim($h ?? '')), array_shift($rows));

        return collect($rows)
            ->filter(fn($row) => !empty(array_filter($row))) // skip empty rows
            ->map(fn($row) => array_combine($headers, array_map('trim', $row)))
            ->values()
            ->toArray();
    }

    // ── Parse supplementary sheets grouped by external_id ──

    private function parseSupplementarySheet($spreadsheet, string $name): array
    {
        $sheet = $spreadsheet->getSheetByName($name);
        if (!$sheet) return [];

        $rows = $this->sheetToArray($sheet);

        return collect($rows)->groupBy('external_id')->toArray();
    }
}



