<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;


// ─────────────────────────────────────────────────────────────
// FILE: database/migrations/2026_03_25_000007_create_quiz_attempts_table.php
// Tracks each user attempt
// ─────────────────────────────────────────────────────────────

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('quiz_attempts', function (Blueprint $table) {
            $table->id();
            $table->foreignId('quiz_id')->constrained()->cascadeOnDelete();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->foreignId('schedule_id')->nullable()->constrained('quiz_schedules')->nullOnDelete();
            $table->unsignedInteger('attempt_number')->default(1);

            $table->enum('status', [
                'in_progress',    // user is currently taking the quiz
                'submitted',      // user clicked submit
                'auto_submitted', // time ran out
                'abandoned',      // user left without submitting
                'grading',        // being graded (for long_answer)
                'completed',      // fully graded, result available
            ])->default('in_progress');

            $table->timestamp('started_at');
            $table->timestamp('submitted_at')->nullable();

            // Time tracking
            $table->unsignedInteger('time_spent_sec')->default(0);
            $table->unsignedInteger('time_allowed_sec')->nullable();

            // Scores (filled after grading)
            $table->decimal('total_marks', 8, 2)->default(0);
            $table->decimal('marks_obtained', 8, 2)->default(0);
            $table->decimal('negative_marks_total', 8, 2)->default(0);
            $table->decimal('final_score', 8, 2)->default(0);       // obtained - negative
            $table->decimal('percentage', 5, 2)->default(0);
            $table->boolean('is_passed')->default(false);
            $table->unsignedInteger('rank')->nullable();

            // Question stats
            $table->unsignedInteger('total_questions')->default(0);
            $table->unsignedInteger('attempted_count')->default(0);
            $table->unsignedInteger('correct_count')->default(0);
            $table->unsignedInteger('incorrect_count')->default(0);
            $table->unsignedInteger('skipped_count')->default(0);

            // Shuffled question order (stored so user sees same order on refresh)
            $table->json('question_order')->nullable();

            $table->string('ip_address', 45)->nullable();
            $table->string('user_agent', 500)->nullable();

            $table->timestamps();

            $table->index(['quiz_id', 'user_id']);
            $table->index(['quiz_id', 'status']);
            $table->index(['user_id', 'status']);
            $table->unique(['quiz_id', 'user_id', 'attempt_number']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('quiz_attempts');
    }
};