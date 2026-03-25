<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
// ─────────────────────────────────────────────────────────────
// FILE: database/migrations/2026_03_25_000003_create_quiz_sections_table.php
// Optional: for exams with sections (e.g. "Physics Section", "Chemistry Section")
// ─────────────────────────────────────────────────────────────

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('quiz_sections', function (Blueprint $table) {
            $table->id();
            $table->foreignId('quiz_id')->constrained()->cascadeOnDelete();
            $table->string('title');                                    // "Section A: Physics"
            $table->text('instructions')->nullable();                   // section-specific instructions
            $table->unsignedInteger('sort_order')->default(0);
            $table->unsignedInteger('duration_min')->nullable();        // per-section timer (optional)
            $table->unsignedInteger('required_questions')->nullable();  // "attempt any 5 of 8"
            $table->timestamps();

            $table->index('quiz_id');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('quiz_sections');
    }
};
