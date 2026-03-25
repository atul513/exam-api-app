<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

// ─────────────────────────────────────────────────────────────
// FILE: database/migrations/2026_03_25_000002_create_quizzes_table.php
// ─────────────────────────────────────────────────────────────

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('quizzes', function (Blueprint $table) {
            $table->id();

            // ── Step 1: Details ──
            $table->string('title');
            $table->string('slug')->unique();
            $table->foreignId('category_id')->nullable()->constrained('quiz_categories')->nullOnDelete();
            $table->enum('type', ['quiz', 'exam'])->default('quiz');
            $table->enum('access_type', ['free', 'paid'])->default('free');
            $table->decimal('price', 8, 2)->nullable();
            $table->text('description')->nullable();
            $table->string('thumbnail_url', 500)->nullable();
            $table->enum('visibility', ['public', 'private'])->default('public');
            $table->enum('status', ['draft', 'published', 'archived'])->default('draft');

            // ── Step 2: Settings ──

            // Duration
            $table->enum('duration_mode', ['manual', 'auto'])->default('manual');
            // manual = admin sets total_duration_min
            // auto = sum of per-question time_limit_sec from question bank
            $table->unsignedInteger('total_duration_min')->nullable();

            // Scoring
            $table->enum('marks_mode', ['question_wise', 'fixed'])->default('question_wise');
            // question_wise = each question has its own marks (from question bank)
            // fixed = all questions have same marks
            $table->decimal('fixed_marks_per_question', 5, 2)->nullable();
            $table->boolean('negative_marking')->default(false);
            $table->decimal('negative_marks_per_question', 5, 2)->nullable();

            // Pass / Fail
            $table->decimal('pass_percentage', 5, 2)->default(33.00);

            // Behavior
            $table->boolean('shuffle_questions')->default(false);
            $table->boolean('shuffle_options')->default(false);
            $table->unsignedInteger('max_attempts')->nullable(); // null = unlimited
            $table->boolean('disable_finish_button')->default(false);
            $table->boolean('enable_question_list_view')->default(true);
            $table->boolean('hide_solutions')->default(false);
            $table->boolean('show_leaderboard')->default(true);
            $table->boolean('show_result_immediately')->default(true);
            $table->boolean('allow_review_after_submit')->default(true);
            $table->boolean('auto_submit_on_timeout')->default(true);

            // Metadata
            $table->string('language', 10)->default('en');
            $table->foreignId('created_by')->constrained('users');
            $table->unsignedBigInteger('total_marks')->default(0);      // denormalized
            $table->unsignedInteger('total_questions')->default(0);     // denormalized

            $table->timestamps();
            $table->softDeletes();

            // Indexes
            $table->index('type');
            $table->index('status');
            $table->index('visibility');
            $table->index('access_type');
            $table->index('category_id');
            $table->index(['status', 'visibility', 'type']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('quizzes');
    }
};