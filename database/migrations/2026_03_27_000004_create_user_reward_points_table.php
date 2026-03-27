<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

// ─────────────────────────────────────────────────────────────
// FILE: database/migrations/2026_03_27_000004_create_user_reward_points_table.php
// Central reward points ledger (if you don't have one already)
// ─────────────────────────────────────────────────────────────

return new class extends Migration
{
    public function up(): void
    {
        if (!Schema::hasTable('user_reward_points')) {
            Schema::create('user_reward_points', function (Blueprint $table) {
                $table->id();
                $table->foreignId('user_id')->constrained()->cascadeOnDelete();
                $table->string('source_type', 50);     // 'practice_set', 'quiz', 'daily_login' etc.
                $table->unsignedBigInteger('source_id')->nullable();
                $table->unsignedBigInteger('question_id')->nullable();
                $table->integer('points');               // can be negative for deductions
                $table->string('description')->nullable();
                $table->timestamps();

                $table->index(['user_id', 'source_type']);
                $table->index('user_id');
            });
        }
    }

    public function down(): void
    {
        Schema::dropIfExists('user_reward_points');
    }
};
