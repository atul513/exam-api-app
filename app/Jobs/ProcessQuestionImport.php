<?php

// ============================================================
// ─── app/Jobs/ProcessQuestionImport.php ─────────────────────
// ============================================================

// namespace App\Jobs;

// use App\Models\{ImportBatch, Question, Subject, Topic, Tag};
// use App\Services\QuestionService;
// use App\Enums\ImportStatus;
// use Illuminate\Bus\Queueable;
// use Illuminate\Contracts\Queue\ShouldQueue;
// use Illuminate\Foundation\Bus\Dispatchable;
// use Illuminate\Queue\{InteractsWithQueue, SerializesModels};
// use Illuminate\Support\Facades\{DB, Log, Storage};
// use PhpOffice\PhpSpreadsheet\IOFactory;

// class ProcessQuestionImport implements ShouldQueue
// {
//     use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

//     public int $timeout = 600;      // 10 minutes max
//     public int $tries = 1;          // don't retry, errors are logged

//     public function __construct(
//         private ImportBatch $batch,
//         private bool $dryRun = false,
//     ) {}

//     public function handle(QuestionService $questionService): void
//     {
//         $this->batch->update([
//             'status'     => ImportStatus::Processing,
//             'started_at' => now(),
//         ]);

//         try {
//             $filePath = Storage::disk('local')->path($this->batch->file_path);
//             $spreadsheet = IOFactory::load($filePath);

//             // ── Parse main questions sheet ──
//             $mainSheet = $spreadsheet->getSheetByName('questions')
//                 ?? $spreadsheet->getSheet(0);

//             $rows = $this->sheetToArray($mainSheet);
//             $totalRows = count($rows);
//             $this->batch->update(['total_rows' => $totalRows]);

//             // ── Parse supplementary sheets ──
//             $fillBlanks = $this->parseSupplementarySheet($spreadsheet, 'fill_blanks');
//             $matchPairs = $this->parseSupplementarySheet($spreadsheet, 'match_pairs');
//             $longAnswers = $this->parseSupplementarySheet($spreadsheet, 'long_answers');

//             // ── Cache lookups (avoid N+1 queries) ──
//             $subjects = Subject::pluck('id', 'code')->toArray();
//             $topics = Topic::pluck('id', 'code')->toArray();

//             $errors = [];
//             $successCount = 0;
//             $batchData = [];

//             foreach ($rows as $index => $row) {
//                 $rowNum = $index + 2; // +2 for 1-indexed + header

//                 try {
//                     // Validate row
//                     $validationErrors = $this->validateRow($row, $rowNum, $subjects, $topics);
//                     if (!empty($validationErrors)) {
//                         $errors = array_merge($errors, $validationErrors);
//                         continue;
//                     }

//                     // Transform row to question data
//                     $questionData = $this->transformRow(
//                         $row, $subjects, $topics,
//                         $fillBlanks, $matchPairs, $longAnswers
//                     );

//                     if (!$this->dryRun) {
//                         // Insert in mini-batches of 50
//                         $batchData[] = [
//                             'data'    => $questionData,
//                             'row_num' => $rowNum,
//                         ];

//                         if (count($batchData) >= 50) {
//                             $successCount += $this->insertBatch($batchData, $questionService);
//                             $batchData = [];
//                         }
//                     } else {
//                         $successCount++;
//                     }

//                 } catch (\Throwable $e) {
//                     $errors[] = [
//                         'row'         => $rowNum,
//                         'external_id' => $row['external_id'] ?? null,
//                         'field'       => 'general',
//                         'error'       => $e->getMessage(),
//                         'severity'    => 'error',
//                     ];
//                 }

//                 // Update progress every 50 rows
//                 if ($index % 50 === 0) {
//                     $this->batch->update([
//                         'processed_rows' => $index + 1,
//                         'success_count'  => $successCount,
//                         'error_count'    => count($errors),
//                     ]);
//                 }
//             }

//             // Insert remaining batch
//             if (!empty($batchData) && !$this->dryRun) {
//                 $successCount += $this->insertBatch($batchData, $questionService);
//             }

//             // Finalize
//             $this->batch->update([
//                 'processed_rows' => $totalRows,
//                 'success_count'  => $successCount,
//                 'error_count'    => count($errors),
//                 'error_log'      => $errors,
//                 'status'         => count($errors) > 0
//                     ? ImportStatus::Partial
//                     : ImportStatus::Completed,
//                 'completed_at'   => now(),
//             ]);

//         } catch (\Throwable $e) {
//             Log::error('Import failed', [
//                 'batch_id' => $this->batch->id,
//                 'error'    => $e->getMessage(),
//             ]);

//             $this->batch->update([
//                 'status'       => ImportStatus::Failed,
//                 'error_log'    => [['row' => 0, 'error' => $e->getMessage(), 'severity' => 'fatal']],
//                 'completed_at' => now(),
//             ]);
//         }
//     }

//     // ── Transform Excel row → question create data ──

//     private function transformRow(
//         array $row, array $subjects, array $topics,
//         array $fillBlanks, array $matchPairs, array $longAnswers
//     ): array {
//         $type = strtolower(trim($row['type']));
//         $extId = $row['external_id'];

//         $data = [
//             'subject_id'       => $subjects[$row['subject_code']],
//             'topic_id'         => $topics[$row['topic_code']],
//             'type'             => $type,
//             'difficulty'       => strtolower(trim($row['difficulty'] ?? 'medium')),
//             'question_text'    => trim($row['question_text']),
//             'marks'            => (float) $row['marks'],
//             'negative_marks'   => (float) ($row['negative_marks'] ?? 0),
//             'time_limit_sec'   => !empty($row['time_limit_sec']) ? (int) $row['time_limit_sec'] : null,
//             'explanation'      => $row['explanation'] ?? null,
//             'solution_approach' => $row['solution_approach'] ?? null,
//             'language'         => $row['language'] ?? 'en',
//             'source'           => $row['source'] ?? null,
//             'external_id'      => $extId,
//             'import_batch_id'  => $this->batch->id,
//         ];

//         // Tags
//         if (!empty($row['tags'])) {
//             $data['tags'] = array_map('trim', explode(',', $row['tags']));
//         }

//         // Options (MCQ, Multi-select, True/False)
//         if (in_array($type, ['mcq', 'multi_select', 'true_false'])) {
//             $correctAnswers = array_map('trim', explode(',', strtoupper($row['correct_answer'] ?? '')));
//             $optionLetters = ['A', 'B', 'C', 'D', 'E'];
//             $options = [];

//             foreach ($optionLetters as $i => $letter) {
//                 $key = 'option_' . strtolower($letter);
//                 if (!empty($row[$key])) {
//                     $options[] = [
//                         'option_text' => trim($row[$key]),
//                         'is_correct'  => in_array($letter, $correctAnswers),
//                         'sort_order'  => $i,
//                     ];
//                 }
//             }
//             $data['options'] = $options;
//         }

//         // Fill blanks
//         if ($type === 'fill_blank' && isset($fillBlanks[$extId])) {
//             $data['blanks'] = collect($fillBlanks[$extId])->map(fn($b) => [
//                 'blank_number'     => (int) $b['blank_number'],
//                 'correct_answers'  => array_map('trim', explode('|', $b['correct_answers'])),
//                 'is_case_sensitive' => strtolower($b['is_case_sensitive'] ?? 'false') === 'true',
//             ])->toArray();
//         }

//         // Match pairs
//         if ($type === 'match_column' && isset($matchPairs[$extId])) {
//             $data['match_pairs'] = collect($matchPairs[$extId])->map(fn($m, $i) => [
//                 'column_a_text' => trim($m['column_a']),
//                 'column_b_text' => trim($m['column_b']),
//                 'sort_order'    => (int) ($m['sort_order'] ?? $i),
//             ])->toArray();
//         }

//         // Short/Long answer
//         if (in_array($type, ['short_answer', 'long_answer'])) {
//             $answerText = $row['correct_answer'] ?? '';
//             $keywords = [];

//             if ($type === 'long_answer' && isset($longAnswers[$extId])) {
//                 $la = $longAnswers[$extId][0]; // take first
//                 $answerText = $la['model_answer'] ?? $answerText;
//                 $keywords = !empty($la['keywords'])
//                     ? array_map('trim', explode(',', $la['keywords']))
//                     : [];
//             }

//             $data['expected_answer'] = [
//                 'answer_text' => $answerText,
//                 'keywords'    => $keywords,
//                 'min_words'   => !empty($longAnswers[$extId][0]['min_words'])
//                     ? (int) $longAnswers[$extId][0]['min_words'] : null,
//                 'max_words'   => !empty($longAnswers[$extId][0]['max_words'])
//                     ? (int) $longAnswers[$extId][0]['max_words'] : null,
//             ];
//         }

//         return $data;
//     }

//     // ── Validate a single row ──

//     private function validateRow(array $row, int $rowNum, array $subjects, array $topics): array
//     {
//         $errors = [];
//         $extId = $row['external_id'] ?? "row_{$rowNum}";

//         $addError = function (string $field, string $msg) use (&$errors, $rowNum, $extId) {
//             $errors[] = [
//                 'row' => $rowNum, 'external_id' => $extId,
//                 'field' => $field, 'error' => $msg, 'severity' => 'error',
//             ];
//         };

//         // Required fields
//         if (empty($row['external_id'])) $addError('external_id', 'External ID is required.');
//         if (empty($row['type'])) $addError('type', 'Question type is required.');
//         if (empty($row['question_text'])) $addError('question_text', 'Question text is required.');
//         if (empty($row['marks'])) $addError('marks', 'Marks is required.');

//         // Type check
//         $validTypes = ['mcq', 'multi_select', 'true_false', 'short_answer', 'long_answer', 'fill_blank', 'match_column'];
//         $type = strtolower(trim($row['type'] ?? ''));
//         if (!in_array($type, $validTypes)) {
//             $addError('type', "Invalid type: {$type}. Must be one of: " . implode(', ', $validTypes));
//         }

//         // Subject/topic exist
//         if (!empty($row['subject_code']) && !isset($subjects[$row['subject_code']])) {
//             $addError('subject_code', "Subject '{$row['subject_code']}' not found.");
//         }
//         if (!empty($row['topic_code']) && !isset($topics[$row['topic_code']])) {
//             $addError('topic_code', "Topic '{$row['topic_code']}' not found.");
//         }

//         // MCQ: must have options and correct answer
//         if ($type === 'mcq') {
//             if (empty($row['option_a']) || empty($row['option_b'])) {
//                 $addError('options', 'MCQ requires at least 2 options (A, B).');
//             }
//             $correct = array_filter(explode(',', strtoupper($row['correct_answer'] ?? '')));
//             if (count($correct) !== 1) {
//                 $addError('correct_answer', 'MCQ must have exactly 1 correct answer.');
//             }
//         }

//         // Multi-select: at least 2 correct
//         if ($type === 'multi_select') {
//             $correct = array_filter(explode(',', strtoupper($row['correct_answer'] ?? '')));
//             if (count($correct) < 2) {
//                 $addError('correct_answer', 'Multi-select must have at least 2 correct answers.');
//             }
//         }

//         // True/false
//         if ($type === 'true_false') {
//             $answer = strtoupper(trim($row['correct_answer'] ?? ''));
//             if (!in_array($answer, ['TRUE', 'FALSE'])) {
//                 $addError('correct_answer', 'True/False answer must be TRUE or FALSE.');
//             }
//         }

//         return $errors;
//     }

//     // ── Insert a batch of questions ──

//     private function insertBatch(array $batchData, QuestionService $service): int
//     {
//         $success = 0;
//         DB::beginTransaction();

//         try {
//             foreach ($batchData as $item) {
//                 $service->create($item['data'], $this->batch->imported_by);
//                 $success++;
//             }
//             DB::commit();
//         } catch (\Throwable $e) {
//             DB::rollBack();
//             Log::error("Batch insert failed at row {$item['row_num']}: " . $e->getMessage());
//         }

//         return $success;
//     }

//     // ── Parse sheet into associative array ──

//     private function sheetToArray($sheet): array
//     {
//         $rows = $sheet->toArray(null, false, true, true);
//         if (empty($rows)) return [];

//         $headers = array_map(fn($h) => strtolower(trim($h ?? '')), array_shift($rows));

//         return collect($rows)
//             ->filter(fn($row) => !empty(array_filter($row))) // skip empty rows
//             ->map(fn($row) => array_combine($headers, array_map('trim', $row)))
//             ->values()
//             ->toArray();
//     }

//     // ── Parse supplementary sheets grouped by external_id ──

//     private function parseSupplementarySheet($spreadsheet, string $name): array
//     {
//         $sheet = $spreadsheet->getSheetByName($name);
//         if (!$sheet) return [];

//         $rows = $this->sheetToArray($sheet);

//         return collect($rows)->groupBy('external_id')->toArray();
//     }
// }

// ─────────────────────────────────────────────────────────────
// FILE: app/Jobs/ProcessQuestionImport.php
// Updated: skip demo rows, handle instructions sheet,
// robust header parsing, better error handling
// ─────────────────────────────────────────────────────────────

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

    public int $timeout = 600;
    public int $tries = 1;

    // Prefixes to skip (demo rows from template)
    private const DEMO_PREFIXES = ['DEMO_', 'demo_', 'SAMPLE_', 'sample_', 'EXAMPLE_', 'example_'];

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

            // ── Parse main questions sheet (skip "instructions" sheet) ──
            $mainSheet = $spreadsheet->getSheetByName('questions')
                ?? $spreadsheet->getSheet(0);

            // If first sheet is "instructions", use the second one
            if (strtolower(trim($mainSheet->getTitle())) === 'instructions') {
                $mainSheet = $spreadsheet->getSheet(1);
            }

            $rows = $this->sheetToArray($mainSheet);

            // Filter out demo/sample rows
            $rows = $this->filterDemoRows($rows);

            // Filter out completely empty rows
            $rows = array_values(array_filter($rows, function ($row) {
                return !empty($row['external_id']) && !empty($row['type']) && !empty($row['question_text']);
            }));

            $totalRows = count($rows);
            $this->batch->update(['total_rows' => $totalRows]);

            if ($totalRows === 0) {
                $this->batch->update([
                    'status'       => ImportStatus::Completed,
                    'completed_at' => now(),
                    'error_log'    => [['row' => 0, 'error' => 'No valid data rows found. Make sure to delete demo rows and add your questions.', 'severity' => 'warning']],
                ]);
                return;
            }

            // ── Parse supplementary sheets ──
            $fillBlanks = $this->parseSupplementarySheet($spreadsheet, 'fill_blanks');
            $matchPairs = $this->parseSupplementarySheet($spreadsheet, 'match_pairs');
            $longAnswers = $this->parseSupplementarySheet($spreadsheet, 'long_answers');

            // ── Cache lookups ──
            $subjects = Subject::pluck('id', 'code')->toArray();
            $topics = Topic::pluck('id', 'code')->toArray();

            $errors = [];
            $successCount = 0;
            $batchData = [];
            $seenExternalIds = [];

            foreach ($rows as $index => $row) {
                $rowNum = $index + 2; // +2 for header + 0-index

                try {
                    // Clean the row data
                    $row = $this->cleanRow($row);

                    // Check for duplicate external_id within this file
                    $extId = $row['external_id'] ?? '';
                    if (isset($seenExternalIds[$extId])) {
                        $errors[] = [
                            'row'         => $rowNum,
                            'external_id' => $extId,
                            'field'       => 'external_id',
                            'error'       => "Duplicate external_id '{$extId}'. First seen at row {$seenExternalIds[$extId]}.",
                            'severity'    => 'error',
                        ];
                        continue;
                    }
                    $seenExternalIds[$extId] = $rowNum;

                    // Validate row
                    $validationErrors = $this->validateRow($row, $rowNum, $subjects, $topics, $fillBlanks, $matchPairs);
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
                        $batchData[] = [
                            'data'    => $questionData,
                            'row_num' => $rowNum,
                        ];

                        if (count($batchData) >= 50) {
                            $successCount += $this->insertBatch($batchData, $questionService, $errors);
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

            // Insert remaining
            if (!empty($batchData) && !$this->dryRun) {
                $successCount += $this->insertBatch($batchData, $questionService, $errors);
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
                'summary'        => [
                    'total_rows'    => $totalRows,
                    'imported'      => $successCount,
                    'errors'        => count($errors),
                    'dry_run'       => $this->dryRun,
                    'demo_skipped'  => true,
                ],
            ]);

        } catch (\Throwable $e) {
            Log::error('Import failed', [
                'batch_id' => $this->batch->id,
                'error'    => $e->getMessage(),
                'trace'    => $e->getTraceAsString(),
            ]);

            $this->batch->update([
                'status'       => ImportStatus::Failed,
                'error_log'    => [['row' => 0, 'error' => $e->getMessage(), 'severity' => 'fatal']],
                'completed_at' => now(),
            ]);
        }
    }

    // ── FILTER DEMO ROWS ──

    private function filterDemoRows(array $rows): array
    {
        return array_values(array_filter($rows, function ($row) {
            $extId = trim($row['external_id'] ?? '');

            if (empty($extId)) return false;

            foreach (self::DEMO_PREFIXES as $prefix) {
                if (str_starts_with($extId, $prefix)) {
                    return false;
                }
            }

            return true;
        }));
    }

    // ── CLEAN ROW ──

    private function cleanRow(array $row): array
    {
        return array_map(function ($value) {
            if ($value === null) return '';
            if (is_string($value)) return trim($value);
            return $value;
        }, $row);
    }

    // ── VALIDATE ROW ──

    private function validateRow(
        array $row, int $rowNum, array $subjects, array $topics,
        array $fillBlanks, array $matchPairs
    ): array {
        $errors = [];
        $extId = $row['external_id'] ?? "row_{$rowNum}";

        $addError = function (string $field, string $msg, string $severity = 'error') use (&$errors, $rowNum, $extId) {
            $errors[] = [
                'row'         => $rowNum,
                'external_id' => $extId,
                'field'       => $field,
                'error'       => $msg,
                'severity'    => $severity,
            ];
        };

        // Required fields
        if (empty($row['external_id']))    $addError('external_id', 'External ID is required.');
        if (empty($row['type']))           $addError('type', 'Question type is required.');
        if (empty($row['question_text']))  $addError('question_text', 'Question text is required.');
        if (empty($row['subject_code']))   $addError('subject_code', 'Subject code is required.');
        if (empty($row['topic_code']))     $addError('topic_code', 'Topic code is required.');

        $marks = $row['marks'] ?? '';
        if ($marks === '' || !is_numeric($marks) || (float) $marks <= 0) {
            $addError('marks', 'Marks must be a positive number.');
        }

        // Type check
        $validTypes = ['mcq', 'multi_select', 'true_false', 'short_answer', 'long_answer', 'fill_blank', 'match_column'];
        $type = strtolower(trim($row['type'] ?? ''));
        if (!empty($type) && !in_array($type, $validTypes)) {
            $addError('type', "Invalid type: '{$type}'. Must be one of: " . implode(', ', $validTypes));
            return $errors; // can't validate further without valid type
        }

        // Difficulty check
        $difficulty = strtolower(trim($row['difficulty'] ?? 'medium'));
        if (!in_array($difficulty, ['easy', 'medium', 'hard', 'expert'])) {
            $addError('difficulty', "Invalid difficulty: '{$difficulty}'. Must be: easy, medium, hard, or expert.", 'warning');
        }

        // Subject exists
        $subCode = trim($row['subject_code'] ?? '');
        if (!empty($subCode) && !isset($subjects[$subCode])) {
            $addError('subject_code', "Subject code '{$subCode}' not found. Check your admin panel for valid codes.");
        }

        // Topic exists
        $topCode = trim($row['topic_code'] ?? '');
        if (!empty($topCode) && !isset($topics[$topCode])) {
            $addError('topic_code', "Topic code '{$topCode}' not found. Check your admin panel for valid codes.");
        }

        // Topic belongs to subject (if both exist)
        if (!empty($subCode) && !empty($topCode) && isset($subjects[$subCode]) && isset($topics[$topCode])) {
            $topicModel = \App\Models\Topic::where('code', $topCode)->first();
            if ($topicModel && $topicModel->subject_id !== $subjects[$subCode]) {
                $addError('topic_code', "Topic '{$topCode}' does not belong to subject '{$subCode}'.");
            }
        }

        // ── Type-specific validation ──

        if ($type === 'mcq') {
            if (empty($row['option_a']) || empty($row['option_b'])) {
                $addError('options', 'MCQ requires at least 2 options (option_a and option_b).');
            }
            $correct = $this->parseCorrectAnswer($row['correct_answer'] ?? '');
            if (count($correct) !== 1) {
                $addError('correct_answer', 'MCQ must have exactly 1 correct answer (e.g., B). Found: ' . count($correct));
            }
            $this->validateCorrectLetters($correct, $row, $addError);
        }

        if ($type === 'multi_select') {
            if (empty($row['option_a']) || empty($row['option_b'])) {
                $addError('options', 'Multi-select requires at least 2 options.');
            }
            $correct = $this->parseCorrectAnswer($row['correct_answer'] ?? '');
            if (count($correct) < 2) {
                $addError('correct_answer', 'Multi-select must have at least 2 correct answers (e.g., A,C,E). Found: ' . count($correct));
            }
            $this->validateCorrectLetters($correct, $row, $addError);
        }

        if ($type === 'true_false') {
            $answer = strtoupper(trim($row['correct_answer'] ?? ''));
            if (!in_array($answer, ['TRUE', 'FALSE'])) {
                $addError('correct_answer', "True/False answer must be TRUE or FALSE. Found: '{$answer}'");
            }
            // Validate option text
            $optA = strtolower(trim($row['option_a'] ?? ''));
            $optB = strtolower(trim($row['option_b'] ?? ''));
            if ($optA !== 'true' || $optB !== 'false') {
                $addError('options', "True/False: option_a must be 'True' and option_b must be 'False'.", 'warning');
            }
        }

        if ($type === 'short_answer') {
            if (empty($row['correct_answer'])) {
                $addError('correct_answer', 'Short answer must have a correct_answer.');
            }
        }

        if ($type === 'fill_blank') {
            // Check {{n}} placeholders exist
            preg_match_all('/\{\{(\d+)\}\}/', $row['question_text'] ?? '', $matches);
            $placeholders = array_map('intval', $matches[1] ?? []);
            if (empty($placeholders)) {
                $addError('question_text', "Fill-blank question must have {{1}}, {{2}} etc. placeholders. None found.");
            }

            // Check fill_blanks sheet has matching rows
            if (!isset($fillBlanks[$extId]) || empty($fillBlanks[$extId])) {
                $addError('fill_blanks', "No matching rows found in 'fill_blanks' sheet for external_id '{$extId}'.");
            } else {
                $blankNumbers = collect($fillBlanks[$extId])->pluck('blank_number')->map(fn($v) => (int) $v)->toArray();
                $missingBlanks = array_diff($placeholders, $blankNumbers);
                if (!empty($missingBlanks)) {
                    $addError('fill_blanks', 'Missing blank definitions in fill_blanks sheet for: {{' . implode('}}, {{', $missingBlanks) . '}}');
                }
                $extraBlanks = array_diff($blankNumbers, $placeholders);
                if (!empty($extraBlanks)) {
                    $addError('fill_blanks', 'Extra blank definitions without matching placeholders: ' . implode(', ', $extraBlanks), 'warning');
                }
            }
        }

        if ($type === 'match_column') {
            if (!isset($matchPairs[$extId]) || count($matchPairs[$extId]) < 2) {
                $addError('match_pairs', "Match-column requires at least 2 pairs in 'match_pairs' sheet for external_id '{$extId}'.");
            }
        }

        // Negative marks warning
        $negMarks = (float) ($row['negative_marks'] ?? 0);
        if ($negMarks > 0 && $negMarks >= (float) ($row['marks'] ?? 0)) {
            $addError('negative_marks', 'Negative marks should be less than positive marks.', 'warning');
        }

        return $errors;
    }

    // ── PARSE CORRECT ANSWER LETTERS ──

    private function parseCorrectAnswer(string $answer): array
    {
        $answer = strtoupper(trim($answer));
        if (empty($answer)) return [];

        return array_values(array_filter(
            array_map('trim', explode(',', $answer)),
            fn($v) => !empty($v)
        ));
    }

    // ── VALIDATE CORRECT LETTERS MATCH FILLED OPTIONS ──

    private function validateCorrectLetters(array $letters, array $row, callable $addError): void
    {
        $letterToKey = ['A' => 'option_a', 'B' => 'option_b', 'C' => 'option_c', 'D' => 'option_d', 'E' => 'option_e'];
        $validLetters = ['A', 'B', 'C', 'D', 'E'];

        foreach ($letters as $letter) {
            if (!in_array($letter, $validLetters)) {
                $addError('correct_answer', "Invalid option letter: '{$letter}'. Must be A, B, C, D, or E.");
                continue;
            }
            $key = $letterToKey[$letter] ?? null;
            if ($key && empty($row[$key])) {
                $addError('correct_answer', "Correct answer includes '{$letter}' but {$key} is empty.");
            }
        }
    }

    // ── TRANSFORM ROW ──

    private function transformRow(
        array $row, array $subjects, array $topics,
        array $fillBlanks, array $matchPairs, array $longAnswers
    ): array {
        $type = strtolower(trim($row['type']));
        $extId = $row['external_id'];

        $data = [
            'subject_id'       => $subjects[trim($row['subject_code'])],
            'topic_id'         => $topics[trim($row['topic_code'])],
            'type'             => $type,
            'difficulty'       => strtolower(trim($row['difficulty'] ?? 'medium')),
            'question_text'    => trim($row['question_text']),
            'marks'            => (float) $row['marks'],
            'negative_marks'   => (float) ($row['negative_marks'] ?? 0),
            'time_limit_sec'   => !empty($row['time_limit_sec']) ? (int) $row['time_limit_sec'] : null,
            'explanation'      => !empty($row['explanation']) ? trim($row['explanation']) : null,
            'solution_approach' => !empty($row['solution_approach']) ? trim($row['solution_approach']) : null,
            'language'         => !empty($row['language']) ? trim($row['language']) : 'en',
            'source'           => !empty($row['source']) ? trim($row['source']) : null,
            'external_id'      => $extId,
            'import_batch_id'  => $this->batch->id,
        ];

        // Tags
        if (!empty($row['tags'])) {
            $data['tags'] = array_filter(array_map('trim', explode(',', $row['tags'])));
        }

        // Options (MCQ, Multi-select, True/False)
        if (in_array($type, ['mcq', 'multi_select', 'true_false'])) {
            $correctAnswers = $this->parseCorrectAnswer($row['correct_answer'] ?? '');
            $optionLetters = ['A', 'B', 'C', 'D', 'E'];
            $options = [];

            if ($type === 'true_false') {
                // Force True/False structure
                $tfAnswer = strtoupper(trim($row['correct_answer'] ?? ''));
                $options = [
                    [
                        'option_text' => 'True',
                        'is_correct'  => $tfAnswer === 'TRUE',
                        'sort_order'  => 0,
                    ],
                    [
                        'option_text' => 'False',
                        'is_correct'  => $tfAnswer === 'FALSE',
                        'sort_order'  => 1,
                    ],
                ];
            } else {
                foreach ($optionLetters as $i => $letter) {
                    $key = 'option_' . strtolower($letter);
                    $text = trim($row[$key] ?? '');
                    if (!empty($text)) {
                        $options[] = [
                            'option_text' => $text,
                            'is_correct'  => in_array($letter, $correctAnswers),
                            'sort_order'  => $i,
                        ];
                    }
                }
            }
            $data['options'] = $options;
        }

        // Fill blanks
        if ($type === 'fill_blank' && isset($fillBlanks[$extId])) {
            $data['blanks'] = collect($fillBlanks[$extId])->map(function ($b) {
                $answers = trim($b['correct_answers'] ?? '');
                return [
                    'blank_number'     => (int) $b['blank_number'],
                    'correct_answers'  => array_filter(array_map('trim', explode('|', $answers))),
                    'is_case_sensitive' => in_array(strtolower(trim($b['is_case_sensitive'] ?? 'false')), ['true', '1', 'yes']),
                ];
            })->toArray();
        }

        // Match pairs
        if ($type === 'match_column' && isset($matchPairs[$extId])) {
            $data['match_pairs'] = collect($matchPairs[$extId])->map(fn($m, $i) => [
                'column_a_text' => trim($m['column_a'] ?? ''),
                'column_b_text' => trim($m['column_b'] ?? ''),
                'sort_order'    => (int) ($m['sort_order'] ?? $i),
            ])->filter(fn($p) => !empty($p['column_a_text']) && !empty($p['column_b_text']))
              ->values()
              ->toArray();
        }

        // Short/Long answer
        if (in_array($type, ['short_answer', 'long_answer'])) {
            $answerText = trim($row['correct_answer'] ?? '');
            $keywords = [];
            $minWords = null;
            $maxWords = null;

            if ($type === 'long_answer' && isset($longAnswers[$extId])) {
                $la = $longAnswers[$extId][0];
                $modelAnswer = trim($la['model_answer'] ?? '');
                if (!empty($modelAnswer)) {
                    $answerText = $modelAnswer;
                }
                if (!empty($la['keywords'])) {
                    $keywords = array_filter(array_map('trim', explode(',', $la['keywords'])));
                }
                $minWords = !empty($la['min_words']) ? (int) $la['min_words'] : null;
                $maxWords = !empty($la['max_words']) ? (int) $la['max_words'] : null;
            }

            // For short answer, treat correct_answer as both answer_text and keyword
            if ($type === 'short_answer' && !empty($answerText)) {
                $keywords = array_unique(array_merge([$answerText], $keywords));
            }

            $data['expected_answer'] = [
                'answer_text' => $answerText,
                'keywords'    => $keywords,
                'min_words'   => $minWords,
                'max_words'   => $maxWords,
            ];
        }

        return $data;
    }

    // ── INSERT BATCH ──

    private function insertBatch(array &$batchData, QuestionService $service, array &$errors): int
    {
        $success = 0;

        foreach ($batchData as $item) {
            DB::beginTransaction();
            try {
                $service->create($item['data'], $this->batch->imported_by);
                $success++;
                DB::commit();
            } catch (\Throwable $e) {
                DB::rollBack();
                $errors[] = [
                    'row'         => $item['row_num'],
                    'external_id' => $item['data']['external_id'] ?? null,
                    'field'       => 'insert',
                    'error'       => $e->getMessage(),
                    'severity'    => 'error',
                ];
                Log::warning("Import row {$item['row_num']} failed: " . $e->getMessage());
            }
        }

        $batchData = [];
        return $success;
    }

    // ── PARSE SHEET TO ARRAY ──

    private function sheetToArray($sheet): array
    {
        $rows = $sheet->toArray(null, false, true, true);
        if (empty($rows)) return [];

        // First row = headers
        $headerRow = array_shift($rows);
        $headers = array_map(function ($h) {
            return strtolower(trim(preg_replace('/[^a-zA-Z0-9_]/', '_', $h ?? '')));
        }, $headerRow);

        // Remove empty header columns
        $headers = array_filter($headers, fn($h) => !empty($h));

        return collect($rows)
            ->filter(function ($row) use ($headers) {
                // Skip completely empty rows
                $values = array_intersect_key($row, $headers);
                return !empty(array_filter($values, fn($v) => $v !== null && $v !== ''));
            })
            ->map(function ($row) use ($headers) {
                $mapped = [];
                foreach ($headers as $col => $header) {
                    $mapped[$header] = isset($row[$col]) ? trim((string) $row[$col]) : '';
                }
                return $mapped;
            })
            ->values()
            ->toArray();
    }

    // ── PARSE SUPPLEMENTARY SHEETS ──

    private function parseSupplementarySheet($spreadsheet, string $name): array
    {
        $sheet = $spreadsheet->getSheetByName($name);
        if (!$sheet) return [];

        $rows = $this->sheetToArray($sheet);

        // Filter out demo rows from supplementary sheets too
        $rows = $this->filterDemoRows($rows);

        return collect($rows)
            ->filter(fn($row) => !empty($row['external_id']))
            ->groupBy('external_id')
            ->toArray();
    }
}


