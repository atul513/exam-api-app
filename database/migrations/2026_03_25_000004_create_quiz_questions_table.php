<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
// ─────────────────────────────────────────────────────────────
// FILE: database/migrations/2026_03_25_000004_create_quiz_questions_table.php
// Pivot: links quizzes to questions from the question bank
// ─────────────────────────────────────────────────────────────

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('quiz_questions', function (Blueprint $table) {
            $table->id();
            $table->foreignId('quiz_id')->constrained()->cascadeOnDelete();
            $table->foreignId('question_id')->constrained()->cascadeOnDelete();
            $table->foreignId('section_id')->nullable()->constrained('quiz_sections')->nullOnDelete();
            $table->unsignedInteger('sort_order')->default(0);

            // Override marks for this quiz (if null, use question bank marks)
            $table->decimal('marks_override', 5, 2)->nullable();
            $table->decimal('negative_marks_override', 5, 2)->nullable();

            $table->timestamps();

            $table->unique(['quiz_id', 'question_id']);
            $table->index(['quiz_id', 'sort_order']);
            $table->index('section_id');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('quiz_questions');
    }
};