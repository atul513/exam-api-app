<?php
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

// ─────────────────────────────────────────────────────────────
// FILE: database/migrations/2026_03_27_000003_create_practice_set_progress_table.php
// Tracks per-question progress for each user (no time limit, no submit)
// ─────────────────────────────────────────────────────────────

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('practice_set_progress', function (Blueprint $table) {
            $table->id();
            $table->foreignId('practice_set_id')->constrained()->cascadeOnDelete();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->foreignId('question_id')->constrained();
            $table->foreignId('practice_set_question_id')->constrained('practice_set_questions');

            // User's answer
            $table->json('selected_option_ids')->nullable();
            $table->text('text_answer')->nullable();
            $table->json('fill_blank_answers')->nullable();
            $table->json('match_pairs_answer')->nullable();

            // Result (graded immediately per question)
            $table->boolean('is_correct')->nullable();
            $table->unsignedInteger('points_earned')->default(0);
            $table->unsignedInteger('attempts')->default(0);
            // how many tries user took on this question

            $table->timestamps();

            $table->unique(['practice_set_id', 'user_id', 'question_id'], 'psp_unique');
            $table->index(['practice_set_id', 'user_id']);
            $table->index('user_id');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('practice_set_progress');
    }
};
