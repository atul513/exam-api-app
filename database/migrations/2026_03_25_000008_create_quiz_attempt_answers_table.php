<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

// ─────────────────────────────────────────────────────────────
// FILE: database/migrations/2026_03_25_000004_create_quiz_questions_table.php
// ─────────────────────────────────────────────────────────────

// ─────────────────────────────────────────────────────────────
// FILE: database/migrations/2026_03_25_000008_create_quiz_attempt_answers_table.php
// Per-question answer within an attempt
// ─────────────────────────────────────────────────────────────

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('quiz_attempt_answers', function (Blueprint $table) {
            $table->id();
            $table->foreignId('attempt_id')->constrained('quiz_attempts')->cascadeOnDelete();
            $table->foreignId('question_id')->constrained();
            $table->foreignId('quiz_question_id')->constrained('quiz_questions');

            // User's answer (format depends on question type)
            $table->json('selected_option_ids')->nullable();    // MCQ, multi_select, true_false
            $table->text('text_answer')->nullable();             // short_answer, long_answer
            $table->json('fill_blank_answers')->nullable();      // {"1":"Jupiter","2":"Mercury"}
            $table->json('match_pairs_answer')->nullable();      // {"mp_1":"mb_3","mp_2":"mb_1"}

            // Grading
            $table->boolean('is_correct')->nullable();           // null = not graded yet
            $table->decimal('marks_awarded', 5, 2)->default(0);
            $table->decimal('negative_marks', 5, 2)->default(0);
            $table->boolean('is_manually_graded')->default(false);
            $table->foreignId('graded_by')->nullable()->constrained('users');
            $table->text('grader_feedback')->nullable();

            // Time tracking
            $table->unsignedInteger('time_spent_sec')->default(0);
            $table->boolean('is_bookmarked')->default(false);    // user flagged for review
            $table->unsignedInteger('visit_count')->default(0);  // how many times user visited

            $table->timestamps();

            $table->unique(['attempt_id', 'question_id']);
            $table->index('attempt_id');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('quiz_attempt_answers');
    }
};

