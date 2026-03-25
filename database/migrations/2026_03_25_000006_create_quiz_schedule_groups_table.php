<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

// ─────────────────────────────────────────────────────────────
// FILE: database/migrations/2026_03_25_000006_create_quiz_schedule_groups_table.php
// Assign schedules to user groups (for private visibility)
// ─────────────────────────────────────────────────────────────

return new class extends Migration
{
    public function up(): void
    {
        // User groups table (if you don't already have one)
        if (!Schema::hasTable('user_groups')) {
            Schema::create('user_groups', function (Blueprint $table) {
                $table->id();
                $table->string('name');
                $table->string('slug')->unique();
                $table->text('description')->nullable();
                $table->boolean('is_active')->default(true);
                $table->timestamps();
            });

            Schema::create('user_group_members', function (Blueprint $table) {
                $table->foreignId('user_group_id')->constrained()->cascadeOnDelete();
                $table->foreignId('user_id')->constrained()->cascadeOnDelete();
                $table->primary(['user_group_id', 'user_id']);
            });
        }

        // Pivot: schedule ↔ user group
        Schema::create('quiz_schedule_groups', function (Blueprint $table) {
            $table->foreignId('schedule_id')->constrained('quiz_schedules')->cascadeOnDelete();
            $table->foreignId('user_group_id')->constrained()->cascadeOnDelete();
            $table->primary(['schedule_id', 'user_group_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('quiz_schedule_groups');
        // Don't drop user_groups/user_group_members as they may be shared
    }
};
