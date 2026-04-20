<?php

// ─────────────────────────────────────────────────────────────
// FILE: database/migrations/2026_04_02_000002_create_pdf_import_questions_table.php
// Staging table — extracted questions before final import
// ─────────────────────────────────────────────────────────────
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('pdf_import_questions', function (Blueprint $table) {
            $table->id();
            $table->foreignId('pdf_import_id')->constrained()->cascadeOnDelete();

            // Position in PDF
            $table->unsignedInteger('page_number');
            $table->unsignedInteger('question_number');   // Q1, Q2...

            // Extracted content
            $table->text('question_text');                // with $LaTeX$ inline
            $table->json('question_images')->nullable();  // cropped diagram URLs
            $table->json('options');
            // [{text: "...", images: [], is_correct: false}, ...]
            $table->string('correct_answer')->nullable(); // "2" or "B" or "TRUE"
            $table->text('explanation')->nullable();
            $table->json('explanation_images')->nullable();

            // AI confidence and detected content types
            $table->decimal('ai_confidence', 4, 2)->nullable(); // 0.00 to 1.00
            $table->boolean('has_equations')->default(false);
            $table->boolean('has_diagrams')->default(false);
            $table->string('detected_type', 20)->default('mcq'); // mcq, true_false etc.
            $table->string('detected_subject')->nullable();       // AI guess
            $table->text('ai_raw_response')->nullable();          // full JSON from Claude

            // Classification (filled by admin during review)
            $table->foreignId('subject_id')->nullable()->constrained()->nullOnDelete();
            $table->foreignId('topic_id')->nullable()->constrained()->nullOnDelete();
            $table->string('type', 30)->nullable();
            $table->enum('difficulty', ['easy', 'medium', 'hard', 'expert'])->nullable();
            $table->decimal('marks', 5, 2)->default(4.00);
            $table->decimal('negative_marks', 5, 2)->default(1.00);

            // Review status
            $table->enum('review_status', [
                'pending',    // not yet reviewed
                'approved',   // ready to import
                'rejected',   // skip this question
                'edited',     // admin made changes
                'imported',   // already in question bank
            ])->default('pending');

            $table->text('reviewer_notes')->nullable();
            $table->foreignId('reviewed_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('reviewed_at')->nullable();

            // Link to created question after import
            $table->foreignId('question_id')->nullable()->constrained()->nullOnDelete();

            $table->timestamps();

            $table->index(['pdf_import_id', 'review_status']);
            $table->index(['pdf_import_id', 'page_number', 'question_number']);
        });
    }

    public function down(): void { Schema::dropIfExists('pdf_import_questions'); }
};
