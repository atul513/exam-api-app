<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

// ─────────────────────────────────────────────────────────────
// FILE: database/migrations/2026_03_27_000001_create_practice_sets_table.php
// ─────────────────────────────────────────────────────────────

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('practice_sets', function (Blueprint $table) {
            $table->id();

            // Details
            $table->string('title');
            $table->string('slug')->unique();
            $table->foreignId('category_id')->nullable()
                  ->constrained('quiz_categories')->nullOnDelete();
            $table->foreignId('subject_id')->nullable()->constrained()->nullOnDelete();
            $table->foreignId('topic_id')->nullable()->constrained()->nullOnDelete();
            $table->text('description')->nullable();
            $table->string('thumbnail_url', 500)->nullable();
            $table->enum('access_type', ['free', 'paid'])->default('free');
            $table->decimal('price', 8, 2)->nullable();
            $table->enum('status', ['draft', 'published'])->default('draft');

            // Reward settings
            $table->boolean('allow_reward_points')->default(false);
            $table->enum('points_mode', ['auto', 'manual'])->default('auto');
            // auto  = reward points = question's marks from question bank
            // manual = use points_per_question below
            $table->unsignedInteger('points_per_question')->nullable();
            $table->boolean('show_reward_popup')->default(false);

            // Denormalized
            $table->unsignedInteger('total_questions')->default(0);

            // Meta
            $table->foreignId('created_by')->constrained('users');
            $table->timestamps();
            $table->softDeletes();

            $table->index('status');
            $table->index('access_type');
            $table->index('category_id');
            $table->index('subject_id');
            $table->index(['status', 'access_type']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('practice_sets');
    }
};
