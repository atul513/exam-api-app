<?php

// ─────────────────────────────────────────────────────────────
// FILE: app/Jobs/ExtractQuestionsFromPdf.php
// The AI extraction job — sends PDF pages to Claude API
// ─────────────────────────────────────────────────────────────

namespace App\Jobs;

use App\Models\{PdfImport, PdfImportQuestion};
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\{InteractsWithQueue, SerializesModels};
use Illuminate\Support\Facades\{Http, Log, Storage};
use Spatie\PdfToImage\Pdf;

class ExtractQuestionsFromPdf implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public int $timeout = 1800; // 30 min for large PDFs
    public int $tries = 1;

    public function __construct(private PdfImport $pdfImport) {}

    public function handle(): void
    {
        $this->pdfImport->update([
            'status' => 'extracting',
            'extraction_started_at' => now(),
        ]);

        try {
            $filePath = Storage::disk('local')->path($this->pdfImport->file_path);

            // Convert PDF pages to images
            $pdf = new Pdf($filePath);
            $pageCount = $pdf->getNumberOfPages();
            $this->pdfImport->update(['total_pages' => $pageCount]);

            $allQuestions = [];
            $errors = [];

            // Process pages in batches of 3 (to stay within Claude's context)
            $batchSize = 3;
            for ($page = 1; $page <= $pageCount; $page += $batchSize) {
                $pagesToProcess = range($page, min($page + $batchSize - 1, $pageCount));
                $pageImages = [];

                foreach ($pagesToProcess as $p) {
                    $imagePath = storage_path("app/pdf_pages/import_{$this->pdfImport->id}_page_{$p}.png");
                    $pdf->selectPage($p)->save($imagePath);
                    $pageImages[$p] = $imagePath;
                }

                try {
                    $extracted = $this->extractWithClaude($pageImages, $pagesToProcess);
                    $allQuestions = array_merge($allQuestions, $extracted);
                } catch (\Throwable $e) {
                    $errors[] = "Pages " . implode(',', $pagesToProcess) . ": " . $e->getMessage();
                    Log::error('PDF extraction failed for pages', ['pages' => $pagesToProcess, 'error' => $e->getMessage()]);
                }

                // Cleanup temp images
                foreach ($pageImages as $img) {
                    if (file_exists($img)) unlink($img);
                }
            }

            // Save extracted questions
            foreach ($allQuestions as $q) {
                $this->saveExtractedQuestion($q);
            }

            $this->pdfImport->update([
                'status' => 'reviewing',
                'total_questions_found' => count($allQuestions),
                'extraction_errors' => $errors ?: null,
                'extraction_completed_at' => now(),
            ]);

        } catch (\Throwable $e) {
            Log::error('PDF extraction job failed', ['pdf_import_id' => $this->pdfImport->id, 'error' => $e->getMessage()]);
            $this->pdfImport->update([
                'status' => 'failed',
                'extraction_errors' => [['error' => $e->getMessage(), 'fatal' => true]],
            ]);
        }
    }

    private function extractWithClaude(array $pageImages, array $pageNumbers): array
    {
        $content = [];

        // Add each page image as base64
        foreach ($pageImages as $pageNum => $imagePath) {
            $imageData = base64_encode(file_get_contents($imagePath));
            $content[] = [
                'type' => 'text',
                'text' => "=== PAGE {$pageNum} ===",
            ];
            $content[] = [
                'type'   => 'image',
                'source' => [
                    'type'       => 'base64',
                    'media_type' => 'image/png',
                    'data'       => $imageData,
                ],
            ];
        }

        $content[] = [
            'type' => 'text',
            'text' => $this->buildExtractionPrompt(),
        ];

        $response = Http::withHeaders([
            'x-api-key'         => config('services.anthropic.key'),
            'anthropic-version' => '2023-06-01',
            'content-type'      => 'application/json',
        ])->timeout(120)->post('https://api.anthropic.com/v1/messages', [
            'model'      => 'claude-sonnet-4-6',
            'max_tokens' => 8000,
            'messages'   => [
                ['role' => 'user', 'content' => $content],
            ],
        ]);

        if (!$response->successful()) {
            throw new \Exception('Claude API error: ' . $response->body());
        }

        $text = collect($response->json('content', []))
            ->where('type', 'text')
            ->pluck('text')
            ->implode('');

        // Extract JSON from response
        preg_match('/\[[\s\S]*\]/m', $text, $matches);
        if (empty($matches[0])) {
            throw new \Exception('No JSON array found in Claude response');
        }

        $questions = json_decode($matches[0], true);
        if (!is_array($questions)) {
            throw new \Exception('Invalid JSON from Claude: ' . $matches[0]);
        }

        return $questions;
    }

    private function buildExtractionPrompt(): string
    {
        return <<<PROMPT
Extract ALL MCQ questions from these exam paper pages.

Return a JSON array. Each question object must have:
{
  "page_number": 2,
  "question_number": 5,
  "question_text": "Full question text. For equations use LaTeX: inline as $equation$ and block as $$equation$$",
  "has_equations": true,
  "has_diagrams": false,
  "options": [
    {"text": "option text with any LaTeX $inline$", "position": 1},
    {"text": "...", "position": 2},
    {"text": "...", "position": 3},
    {"text": "...", "position": 4}
  ],
  "correct_answer": "2",
  "explanation": "Full explanation text with LaTeX where needed. Empty string if no solution shown.",
  "detected_subject": "Physics",
  "ai_confidence": 0.95
}

CRITICAL RULES:
1. Convert ALL mathematical equations, fractions, integrals, Greek letters to proper LaTeX
   - Inline: $\\frac{d^2y}{dx^2} = \\frac{\\rho g}{S}$
   - Block: $$v_0^2 = v^2\\left[3 + \\frac{2}{\\sin\\theta}\\right]$$
   - Greek: \\rho, \\theta, \\Delta, \\mu_k, \\omega
   - Superscript: x^{-31}, 10^{-19}
   - Subscript: v_0, F_A, x_1
2. For circuit diagrams, electric field diagrams, graphs: set has_diagrams=true, leave question_text as the text only
3. correct_answer is the OPTION NUMBER (1, 2, 3, or 4) — find it from "Answer (X)" label
4. Extract explanation from "Sol." section completely including all steps
5. If you cannot read part of the equation, use [UNCLEAR] placeholder
6. Return ONLY a valid JSON array, no other text before or after

PROMPT;
    }

    private function saveExtractedQuestion(array $q): void
    {
        // Handle options — convert to our format
        $options = collect($q['options'] ?? [])->map(fn($opt, $i) => [
            'text'       => $opt['text'] ?? '',
            'images'     => [],
            'is_correct' => (string) ($opt['position'] ?? ($i + 1)) === (string) ($q['correct_answer'] ?? ''),
        ])->values()->toArray();

        PdfImportQuestion::create([
            'pdf_import_id'     => $this->pdfImport->id,
            'page_number'       => $q['page_number'] ?? 0,
            'question_number'   => $q['question_number'] ?? 0,
            'question_text'     => $q['question_text'] ?? '',
            'question_images'   => [],
            'options'           => $options,
            'correct_answer'    => $q['correct_answer'] ?? null,
            'explanation'       => $q['explanation'] ?? null,
            'explanation_images' => [],
            'ai_confidence'     => $q['ai_confidence'] ?? null,
            'has_equations'     => $q['has_equations'] ?? false,
            'has_diagrams'      => $q['has_diagrams'] ?? false,
            'detected_type'     => 'mcq',
            'detected_subject'  => $q['detected_subject'] ?? null,
            'ai_raw_response'   => json_encode($q),
            'subject_id'        => $this->pdfImport->subject_id,
            'topic_id'          => $this->pdfImport->topic_id,
            'type'              => 'mcq',
            'difficulty'        => $this->pdfImport->difficulty,
            'marks'             => 4.00,
            'negative_marks'    => 1.00,
            'review_status'     => 'pending',
        ]);
    }
}
