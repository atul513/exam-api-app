<?php

// // ============================================================
// // PHASE 5: EXCEL BULK IMPORT (maatwebsite/excel)
// // ============================================================
// // Install: composer require maatwebsite/excel
// // Config:  php artisan vendor:publish --provider="Maatwebsite\Excel\ExcelServiceProvider" --tag=config


// // ─── app/Http/Controllers/Api/V1/QuestionImportController.php

// namespace App\Http\Controllers\Api\V1;

// use App\Http\Controllers\Controller;
// use App\Models\ImportBatch;
// use App\Jobs\ProcessQuestionImport;
// use App\Exports\ImportTemplateExport;
// use App\Exports\ImportErrorExport;
// use Illuminate\Http\Request;
// use Illuminate\Http\JsonResponse;
// use Maatwebsite\Excel\Facades\Excel;
// use App\Models\{Subject, Topic};
// class QuestionImportController extends Controller
// {
//     /**
//      * POST /api/v1/questions/import
//      * Upload Excel file → queue processing.
//      */
//     public function upload(Request $request): JsonResponse
//     {
//         $request->validate([
//             'file'    => 'required|file|mimes:xlsx,xls|max:20480', // 20MB
//             'dry_run' => 'nullable|boolean',
//         ]);

//         $file = $request->file('file');
//         $path = $file->store('imports/questions', 'local');

//         // Create batch record
//         $batch = ImportBatch::create([
//             'file_name'       => $file->getClientOriginalName(),
//             'file_path'       => $path,
//             'file_size_bytes' => $file->getSize(),
//             'status'          => 'pending',
//             'imported_by'     => $request->user()->id,
//         ]);

//         // Dispatch async job
//         ProcessQuestionImport::dispatch($batch, $request->boolean('dry_run'));

//         return response()->json([
//             'message'  => 'File uploaded. Processing started.',
//             'batch_id' => $batch->id,
//             'status'   => 'pending',
//         ], 202);
//     }

//     /**
//      * GET /api/v1/questions/import/{batch}/status
//      * Poll for import progress.
//      */
//     public function status(ImportBatch $batch): JsonResponse
//     {
//         return response()->json([
//             'batch_id'         => $batch->id,
//             'file_name'        => $batch->file_name,
//             'status'           => $batch->status->value,
//             'total_rows'       => $batch->total_rows,
//             'processed_rows'   => $batch->processed_rows,
//             'success_count'    => $batch->success_count,
//             'error_count'      => $batch->error_count,
//             'progress_percent' => $batch->progressPercent(),
//             'errors_preview'   => array_slice($batch->error_log ?? [], 0, 20),
//             'started_at'       => $batch->started_at?->toISOString(),
//             'completed_at'     => $batch->completed_at?->toISOString(),
//         ]);
//     }

//     /**
//      * GET /api/v1/questions/import/{batch}/errors?format=xlsx|json
//      */
//     public function errors(Request $request, ImportBatch $batch)
//     {
//         if ($request->query('format') === 'xlsx') {
//             return Excel::download(
//                 new ImportErrorExport($batch),
//                 "import-errors-{$batch->id}.xlsx"
//             );
//         }

//         return response()->json([
//             'batch_id' => $batch->id,
//             'errors'   => $batch->error_log,
//         ]);
//     }

//     /**
//      * GET /api/v1/questions/import/batches
//      */
//     public function batches(Request $request): JsonResponse
//     {
//         $batches = ImportBatch::with('importer:id,name')
//             ->when($request->status, fn($q, $s) => $q->where('status', $s))
//             ->latest()
//             ->paginate(20);

//         return response()->json($batches);
//     }

//     /**
//      * DELETE /api/v1/questions/import/{batch}
//      * Rollback: delete all questions from this batch.
//      */
//     public function rollback(ImportBatch $batch): JsonResponse
//     {
//         $count = $batch->questions()->count();
//         $batch->questions()->delete();
//         $batch->update(['status' => 'failed', 'summary' => ['rolled_back' => true]]);

//         return response()->json([
//             'message' => "{$count} questions from this batch have been deleted.",
//         ]);
//     }

//     /**
//      * GET /api/v1/questions/import/template
//      * Download blank Excel template.
//      */
//     // public function downloadTemplate()
//     // {
//     //     return Excel::download(new ImportTemplateExport, 'question-import-template.xlsx');
//     // }


    
// /**
//  * GET /api/v1/questions/import/template?subject_id=1&topic_id=3
//  * Download Excel template with demo data + pre-filled subject/topic.
//  */
// public function downloadTemplate(Request $request)
// {
//     $request->validate([
//         'subject_id' => 'nullable|exists:subjects,id',
//         'topic_id'   => 'nullable|exists:topics,id',
//     ]);

//     $subjectCode = null;
//     $topicCode = null;

//     if ($request->filled('subject_id')) {
//         $subject = Subject::find($request->subject_id);
//         $subjectCode = $subject?->code;
//     }

//     if ($request->filled('topic_id')) {
//         $topic = Topic::find($request->topic_id);
//         $topicCode = $topic?->code;

//         // Auto-detect subject from topic if not provided
//         if (!$subjectCode && $topic) {
//             $subjectCode = $topic->subject?->code;
//         }
//     }

//     return Excel::download(
//         new ImportTemplateExport($subjectCode, $topicCode),
//         'question-import-template.xlsx'
//     );
// }
// }

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Models\{ImportBatch, Subject, Topic};
use App\Jobs\ProcessQuestionImport;
use App\Services\JsonQuestionImportService;
use App\Exports\{ImportTemplateExport, ImportErrorExport};
use Illuminate\Http\Request;
use Illuminate\Http\JsonResponse;
use Maatwebsite\Excel\Facades\Excel;

class QuestionImportController extends Controller
{
    /**
     * POST /api/v1/questions/import-json
     * Upload a JSON file of questions. Subject & topic are chosen up-front
     * (the frontend provides subject_id + topic_id) and override any codes
     * inside the JSON items.
     */
    public function uploadJson(Request $request, JsonQuestionImportService $jsonImporter): JsonResponse
    {
        $data = $request->validate([
            'file'       => 'required|file|mimes:json,txt|max:20480',
            'subject_id' => 'required|integer|exists:subjects,id',
            'topic_id'   => 'required|integer|exists:topics,id',
        ]);

        // Ensure topic belongs to subject
        $topicBelongs = Topic::where('id', $data['topic_id'])
            ->where('subject_id', $data['subject_id'])
            ->exists();
        if (!$topicBelongs) {
            return response()->json([
                'message' => 'The selected topic does not belong to the selected subject.',
            ], 422);
        }

        $file = $request->file('file');
        $path = $file->store('imports/questions-json', 'local');

        $batch = ImportBatch::create([
            'file_name'       => $file->getClientOriginalName(),
            'file_path'       => $path,
            'file_size_bytes' => $file->getSize(),
            'status'          => 'pending',
            'imported_by'     => $request->user()->id,
        ]);

        $batch = $jsonImporter->process(
            $batch,
            (int) $data['subject_id'],
            (int) $data['topic_id'],
            $request->user()->id,
        );

        return response()->json([
            'message'        => 'JSON import completed.',
            'batch_id'       => $batch->id,
            'status'         => $batch->status->value,
            'total_rows'     => $batch->total_rows,
            'success_count'  => $batch->success_count,
            'error_count'    => $batch->error_count,
            'errors_preview' => array_slice($batch->error_log ?? [], 0, 20),
            'summary'        => $batch->summary,
        ], $batch->error_count > 0 ? 200 : 201);
    }

    /**
     * POST /api/v1/questions/import
     */
    public function upload(Request $request): JsonResponse
    {
        $request->validate([
            'file'    => 'required|file|mimes:xlsx,xls|max:20480',
            'dry_run' => 'nullable|boolean',
        ]);

        $file = $request->file('file');
        $path = $file->store('imports/questions', 'local');

        $batch = ImportBatch::create([
            'file_name'       => $file->getClientOriginalName(),
            'file_path'       => $path,
            'file_size_bytes' => $file->getSize(),
            'status'          => 'pending',
            'imported_by'     => $request->user()->id,
        ]);

        $isDryRun = $request->boolean('dry_run');

        if ($isDryRun) {
            // Run synchronously so the result is available immediately
            ProcessQuestionImport::dispatchSync($batch, true);
            $batch->refresh();

            return response()->json([
                'message'          => 'Dry run complete. No data was imported.',
                'batch_id'         => $batch->id,
                'status'           => $batch->status->value,
                'total_rows'       => $batch->total_rows,
                'success_count'    => $batch->success_count,
                'error_count'      => $batch->error_count,
                'errors_preview'   => array_slice($batch->error_log ?? [], 0, 20),
                'summary'          => $batch->summary,
            ]);
        }

        ProcessQuestionImport::dispatchSync($batch, false);
        $batch->refresh();

        return response()->json([
            'message'        => 'Import completed.',
            'batch_id'       => $batch->id,
            'status'         => $batch->status->value,
            'total_rows'     => $batch->total_rows,
            'success_count'  => $batch->success_count,
            'error_count'    => $batch->error_count,
            'errors_preview' => array_slice($batch->error_log ?? [], 0, 20),
        ], $batch->error_count > 0 ? 200 : 201);
    }

    /**
     * GET /api/v1/questions/import/{batch}/status
     */
    public function status(ImportBatch $batch): JsonResponse
    {
        return response()->json([
            'batch_id'         => $batch->id,
            'file_name'        => $batch->file_name,
            'status'           => $batch->status->value,
            'total_rows'       => $batch->total_rows,
            'processed_rows'   => $batch->processed_rows,
            'success_count'    => $batch->success_count,
            'error_count'      => $batch->error_count,
            'progress_percent' => $batch->progressPercent(),
            'errors_preview'   => array_slice($batch->error_log ?? [], 0, 20),
            'started_at'       => $batch->started_at?->toISOString(),
            'completed_at'     => $batch->completed_at?->toISOString(),
        ]);
    }

    /**
     * GET /api/v1/questions/import/{batch}/errors?format=xlsx|json
     */
    public function errors(Request $request, ImportBatch $batch)
    {
        if ($request->query('format') === 'xlsx') {
            return Excel::download(
                new ImportErrorExport($batch),
                "import-errors-{$batch->id}.xlsx"
            );
        }

        return response()->json([
            'batch_id' => $batch->id,
            'errors'   => $batch->error_log,
        ]);
    }

    /**
     * GET /api/v1/questions/import/batches
     */
    public function batches(Request $request): JsonResponse
    {
        $batches = ImportBatch::with('importer:id,name')
            ->when($request->status, fn($q, $s) => $q->where('status', $s))
            ->latest()
            ->paginate(20);

        return response()->json($batches);
    }

    /**
     * DELETE /api/v1/questions/import/{batch}
     */
    public function rollback(ImportBatch $batch): JsonResponse
    {
        $count = $batch->questions()->count();
        $batch->questions()->delete();
        $batch->update(['status' => 'failed', 'summary' => ['rolled_back' => true]]);

        return response()->json([
            'message' => "{$count} questions from this batch have been deleted.",
        ]);
    }

    /**
     * GET /api/v1/questions/import/template?subject_id=1&topic_id=3
     */
    public function downloadTemplate(Request $request)
    {
        $request->validate([
            'subject_id' => 'nullable|exists:subjects,id',
            'topic_id'   => 'nullable|exists:topics,id',
        ]);

        $subjectCode = null;
        $topicCode = null;

        if ($request->filled('subject_id')) {
            $subject = Subject::find($request->subject_id);
            $subjectCode = $subject?->code;
        }

        if ($request->filled('topic_id')) {
            $topic = Topic::find($request->topic_id);
            $topicCode = $topic?->code;

            if (!$subjectCode && $topic) {
                $subjectCode = $topic->subject?->code;
            }
        }

        return Excel::download(
            new ImportTemplateExport($subjectCode, $topicCode),
            'question-import-template.xlsx'
        );
    }
}