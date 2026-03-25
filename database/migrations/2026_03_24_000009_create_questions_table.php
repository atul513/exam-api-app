<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('questions', function (Blueprint $table) {
            $table->id();

            // Classification
            $table->foreignId('subject_id')->constrained();
            $table->foreignId('topic_id')->constrained();
            $table->enum('type', [
                'mcq', 'multi_select', 'true_false',
                'short_answer', 'long_answer',
                'fill_blank', 'match_column'
            ]);
            $table->enum('difficulty', ['easy', 'medium', 'hard', 'expert'])
                  ->default('medium');
            $table->enum('status', ['draft', 'review', 'approved', 'archived', 'rejected'])
                  ->default('draft');

            // Content
            $table->text('question_text');
            $table->json('question_media')->nullable();

            // Scoring
            $table->decimal('marks', 5, 2)->default(1.00);
            $table->decimal('negative_marks', 5, 2)->default(0.00);
            $table->unsignedInteger('time_limit_sec')->nullable();

            // Explanation
            $table->text('explanation')->nullable();
            $table->json('explanation_media')->nullable();
            $table->text('solution_approach')->nullable();

            // Metadata
            $table->string('language', 10)->default('en');
            $table->string('source', 255)->nullable();
            $table->foreignId('created_by')->nullable()->constrained('users');
            $table->foreignId('reviewed_by')->nullable()->constrained('users');
            $table->unsignedBigInteger('import_batch_id')->nullable();
            $table->string('external_id', 255)->nullable();

            // Denormalized stats
            $table->unsignedInteger('times_used')->default(0);
            $table->unsignedInteger('times_correct')->default(0);
            $table->unsignedInteger('times_incorrect')->default(0);
            $table->decimal('avg_time_sec', 8, 2)->nullable();

            $table->timestamps();
            $table->softDeletes();

            // Indexes
            $table->index('subject_id');
            $table->index('topic_id');
            $table->index('type');
            $table->index('difficulty');
            $table->index('status');
            $table->index('import_batch_id');
            $table->index('created_at');
            $table->index(['subject_id', 'topic_id', 'type', 'difficulty', 'status'], 'idx_questions_filter');
            $table->fullText('question_text');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('questions');
    }
};