<?php


// ─────────────────────────────────────────────────────────────
// FILE: app/Http/Controllers/Api/V1/PdfImportController.php
// ─────────────────────────────────────────────────────────────

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Models\{PdfImport, PdfImportQuestion};
use App\Jobs\ExtractQuestionsFromPdf;
use App\Services\QuestionService;
use Illuminate\Http\Request;
use Illuminate\Http\JsonResponse;

class PdfImportController extends Controller
{
    /**
     * POST /api/v1/pdf-imports
     * Upload a PDF exam paper.
     */
    public function upload(Request $request): JsonResponse
    {
        $request->validate([
            'file'            => 'required|file|mimes:pdf|max:51200', // 50MB
            'exam_name'       => 'nullable|string|max:255',
            'exam_year'       => 'nullable|string|max:10',
            'booklet_code'    => 'nullable|string|max:20',
            'subject_id'      => 'nullable|exists:subjects,id',
            'topic_id'        => 'nullable|exists:topics,id',
            'exam_section_id' => 'nullable|exists:exam_sections,id',
            'difficulty'      => 'nullable|in:easy,medium,hard,expert',
        ]);

        $file = $request->file('file');
        $path = $file->store('pdf_imports', 'local');

        $import = PdfImport::create([
            'file_name'       => $file->getClientOriginalName(),
            'file_path'       => $path,
            'file_size_bytes' => $file->getSize(),
            'exam_name'       => $request->exam_name,
            'exam_year'       => $request->exam_year,
            'booklet_code'    => $request->booklet_code,
            'subject_id'      => $request->subject_id,
            'topic_id'        => $request->topic_id,
            'exam_section_id' => $request->exam_section_id,
            'difficulty'      => $request->difficulty ?? 'medium',
            'uploaded_by'     => $request->user()->id,
        ]);

        // Queue the extraction job
        ExtractQuestionsFromPdf::dispatch($import);

        return response()->json([
            'message'   => 'PDF uploaded. AI extraction started.',
            'import_id' => $import->id,
            'status'    => 'extracting',
        ], 202);
    }

    /**
     * GET /api/v1/pdf-imports/{import}/status
     * Poll extraction progress.
     */
    public function status(PdfImport $import): JsonResponse
    {
        return response()->json([
            'id'                       => $import->id,
            'file_name'                => $import->file_name,
            'exam_name'                => $import->exam_name,
            'status'                   => $import->status,
            'total_pages'              => $import->total_pages,
            'total_questions_found'    => $import->total_questions_found,
            'approved_count'           => $import->approved_count,
            'rejected_count'           => $import->rejected_count,
            'imported_count'           => $import->imported_count,
            'pending_review'           => PdfImportQuestion::where('pdf_import_id', $import->id)
                                            ->where('review_status', 'pending')->count(),
            'extraction_errors'        => $import->extraction_errors,
            'extraction_started_at'    => $import->extraction_started_at?->toISOString(),
            'extraction_completed_at'  => $import->extraction_completed_at?->toISOString(),
        ]);
    }

    /**
     * GET /api/v1/pdf-imports/{import}/questions
     * Get extracted questions for review UI.
     */
    public function questions(Request $request, PdfImport $import): JsonResponse
    {
        $questions = PdfImportQuestion::where('pdf_import_id', $import->id)
            ->when($request->review_status, fn($q, $s) => $q->where('review_status', $s))
            ->with(['subject:id,name', 'topic:id,name', 'reviewer:id,name'])
            ->orderBy('page_number')
            ->orderBy('question_number')
            ->paginate(20);

        return response()->json($questions);
    }

    /**
     * PUT /api/v1/pdf-import-questions/{question}
     * Admin edits a single extracted question.
     */
    public function updateQuestion(Request $request, PdfImportQuestion $question): JsonResponse
    {
        $data = $request->validate([
            'question_text'   => 'sometimes|string',
            'options'         => 'sometimes|array',
            'correct_answer'  => 'nullable|string',
            'explanation'     => 'nullable|string',
            'subject_id'      => 'nullable|exists:subjects,id',
            'topic_id'        => 'nullable|exists:topics,id',
            'type'            => 'nullable|string',
            'difficulty'      => 'nullable|in:easy,medium,hard,expert',
            'marks'           => 'nullable|numeric|min:0',
            'negative_marks'  => 'nullable|numeric|min:0',
            'review_status'   => 'nullable|in:approved,rejected,edited,pending',
            'reviewer_notes'  => 'nullable|string',
        ]);

        if (isset($data['review_status'])) {
            $data['reviewed_by'] = $request->user()->id;
            $data['reviewed_at'] = now();
        }

        if (isset($data['question_text']) || isset($data['options'])) {
            $data['review_status'] = 'edited';
        }

        $question->update($data);

        // Update parent import counts
        $this->syncImportCounts($question->pdf_import_id);

        return response()->json(['message' => 'Updated.', 'data' => $question->fresh()]);
    }

    /**
     * POST /api/v1/pdf-imports/{import}/bulk-approve
     * Approve all pending questions at once.
     */
    public function bulkApprove(Request $request, PdfImport $import): JsonResponse
    {
        $data = $request->validate([
            'question_ids' => 'nullable|array',  // null = approve all pending
            'subject_id'   => 'nullable|exists:subjects,id',
            'topic_id'     => 'nullable|exists:topics,id',
        ]);

        $query = PdfImportQuestion::where('pdf_import_id', $import->id)
            ->whereIn('review_status', ['pending', 'edited']);

        if (!empty($data['question_ids'])) {
            $query->whereIn('id', $data['question_ids']);
        }

        $updates = [
            'review_status' => 'approved',
            'reviewed_by'   => $request->user()->id,
            'reviewed_at'   => now(),
        ];

        if (!empty($data['subject_id'])) $updates['subject_id'] = $data['subject_id'];
        if (!empty($data['topic_id'])) $updates['topic_id'] = $data['topic_id'];

        $count = $query->update($updates);
        $this->syncImportCounts($import->id);

        return response()->json(['message' => "{$count} questions approved."]);
    }

    /**
     * POST /api/v1/pdf-imports/{import}/import-approved
     * Import all approved questions into the question bank.
     */
    public function importApproved(Request $request, PdfImport $import): JsonResponse
    {
        $approved = PdfImportQuestion::where('pdf_import_id', $import->id)
            ->where('review_status', 'approved')
            ->get();

        if ($approved->isEmpty()) {
            return response()->json(['message' => 'No approved questions to import.'], 422);
        }

        $questionService = app(\App\Services\QuestionService::class);
        $imported = 0;
        $errors = [];

        $import->update(['status' => 'importing']);

        foreach ($approved as $pq) {
            try {
                if (!$pq->subject_id) {
                    $errors[] = "Q{$pq->question_number}: subject_id required";
                    continue;
                }

                // Build options for question bank
                $options = collect($pq->options)->map(fn($opt, $i) => [
                    'option_text' => $opt['text'] ?? '',
                    'option_media' => !empty($opt['images']) ? array_map(fn($img) => ['type' => 'image', 'url' => $img], $opt['images']) : [],
                    'is_correct'  => $opt['is_correct'] ?? false,
                    'sort_order'  => $i,
                ])->toArray();

                $question = $questionService->create([
                    'subject_id'      => $pq->subject_id,
                    'topic_id'        => $pq->topic_id,
                    'type'            => $pq->type ?? 'mcq',
                    'difficulty'      => $pq->difficulty ?? 'medium',
                    'question_text'   => $pq->question_text,
                    'question_media'  => !empty($pq->question_images)
                        ? array_map(fn($img) => ['type' => 'image', 'url' => $img], $pq->question_images)
                        : [],
                    'marks'           => $pq->marks,
                    'negative_marks'  => $pq->negative_marks,
                    'explanation'     => $pq->explanation,
                    'explanation_media' => !empty($pq->explanation_images)
                        ? array_map(fn($img) => ['type' => 'image', 'url' => $img], $pq->explanation_images)
                        : [],
                    'options'         => $options,
                    'source'          => $import->exam_name . ($import->exam_year ? ' ' . $import->exam_year : ''),
                    'tags'            => array_filter([
                        $import->exam_name ? \Illuminate\Support\Str::slug($import->exam_name) : null,
                        $import->exam_year ? 'pyq-' . $import->exam_year : null,
                    ]),
                ], $request->user()->id);

                $pq->update(['review_status' => 'imported', 'question_id' => $question->id]);
                $imported++;

            } catch (\Throwable $e) {
                $errors[] = "Q{$pq->question_number}: " . $e->getMessage();
            }
        }

        $import->update([
            'status'         => 'completed',
            'imported_count' => $import->imported_count + $imported,
        ]);

        $this->syncImportCounts($import->id);

        return response()->json([
            'message'  => "{$imported} questions imported into question bank.",
            'imported' => $imported,
            'errors'   => $errors,
        ]);
    }

    /**
     * GET /api/v1/pdf-imports
     * List all PDF imports.
     */
    public function index(Request $request): JsonResponse
    {
        $imports = PdfImport::with('uploader:id,name')
            ->when($request->status, fn($q, $s) => $q->where('status', $s))
            ->orderByDesc('created_at')
            ->paginate(20);

        return response()->json($imports);
    }

    private function syncImportCounts(int $importId): void
    {
        $counts = PdfImportQuestion::where('pdf_import_id', $importId)
            ->selectRaw('
                SUM(CASE WHEN review_status = "approved" THEN 1 ELSE 0 END) as approved,
                SUM(CASE WHEN review_status = "rejected" THEN 1 ELSE 0 END) as rejected,
                SUM(CASE WHEN review_status = "imported" THEN 1 ELSE 0 END) as imported
            ')->first();

        PdfImport::where('id', $importId)->update([
            'approved_count' => $counts->approved ?? 0,
            'rejected_count' => $counts->rejected ?? 0,
            'imported_count' => $counts->imported ?? 0,
        ]);
    }
}
