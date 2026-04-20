<?php

// ============================================================
// PDF QUESTION IMPORT SYSTEM
// Uses Claude API to extract questions from PDFs
// Handles: text, LaTeX equations, circuit diagrams
// ============================================================
// Install: composer require spatie/pdf-to-image
//          (requires Imagick PHP extension + Ghostscript)


// ─────────────────────────────────────────────────────────────
// FILE: database/migrations/2026_04_02_000001_create_pdf_imports_table.php
// ─────────────────────────────────────────────────────────────

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('pdf_imports', function (Blueprint $table) {
            $table->id();
            $table->string('file_name', 500);
            $table->string('file_path', 1000);
            $table->unsignedBigInteger('file_size_bytes')->nullable();
            $table->unsignedInteger('total_pages')->default(0);

            // Metadata about the exam
            $table->string('exam_name')->nullable();         // "NEET UG 2025"
            $table->string('exam_year', 10)->nullable();     // "2025"
            $table->string('booklet_code', 20)->nullable();  // "45"
            $table->foreignId('subject_id')->nullable()->constrained()->nullOnDelete();
            $table->foreignId('topic_id')->nullable()->constrained()->nullOnDelete();
            $table->foreignId('exam_section_id')->nullable()->constrained('exam_sections')->nullOnDelete();
            $table->enum('difficulty', ['easy', 'medium', 'hard', 'expert'])->default('medium');

            $table->enum('status', [
                'pending',      // uploaded, not processed
                'extracting',   // AI extraction in progress
                'reviewing',    // extraction done, awaiting human review
                'importing',    // approved questions being imported
                'completed',    // all done
                'failed',       // extraction failed
            ])->default('pending');

            $table->unsignedInteger('total_questions_found')->default(0);
            $table->unsignedInteger('approved_count')->default(0);
            $table->unsignedInteger('rejected_count')->default(0);
            $table->unsignedInteger('imported_count')->default(0);

            $table->json('extraction_errors')->nullable();
            $table->foreignId('uploaded_by')->constrained('users');
            $table->timestamp('extraction_started_at')->nullable();
            $table->timestamp('extraction_completed_at')->nullable();
            $table->timestamps();

            $table->index('status');
            $table->index('uploaded_by');
        });
    }

    public function down(): void { Schema::dropIfExists('pdf_imports'); }
};
