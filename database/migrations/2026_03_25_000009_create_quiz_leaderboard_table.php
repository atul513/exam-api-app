<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

// ─────────────────────────────────────────────────────────────
// FILE: database/migrations/2026_03_25_000004_create_quiz_questions_table.php
// ─────────────────────────────────────────────────────────────
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('quiz_leaderboard', function (Blueprint $table) {
            $table->id();
            $table->foreignId('quiz_id')->constrained()->cascadeOnDelete();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->foreignId('attempt_id')->constrained('quiz_attempts')->cascadeOnDelete();
            $table->decimal('final_score', 8, 2);
            $table->decimal('percentage', 5, 2);
            $table->unsignedInteger('time_spent_sec');
            $table->unsignedInteger('correct_count');
            $table->unsignedInteger('rank')->default(0);
            $table->timestamps();

            $table->unique(['quiz_id', 'user_id']);  // best attempt only
            $table->index(['quiz_id', 'rank']);
            $table->index(['quiz_id', 'final_score', 'time_spent_sec']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('quiz_leaderboard');
    }
};