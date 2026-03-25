<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

// ─────────────────────────────────────────────────────────────
// FILE: database/migrations/2026_03_25_000005_create_quiz_schedules_table.php
// Multiple schedule windows per quiz
// ─────────────────────────────────────────────────────────────

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('quiz_schedules', function (Blueprint $table) {
            $table->id();
            $table->foreignId('quiz_id')->constrained()->cascadeOnDelete();
            $table->string('title')->nullable();                // "Morning Batch", "Batch A"
            $table->timestamp('starts_at');
            $table->timestamp('ends_at');
            $table->unsignedInteger('grace_period_min')->default(0);  // extra minutes after ends_at
            $table->boolean('is_active')->default(true);
            $table->timestamps();

            $table->index(['quiz_id', 'starts_at', 'ends_at']);
            $table->index('is_active');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('quiz_schedules');
    }
};