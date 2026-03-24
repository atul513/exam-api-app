<?php

// ============================================================
// PHASE 1: DATABASE MIGRATIONS (MySQL 8.0+)
// Run: php artisan make:migration create_question_bank_tables
// ============================================================

// File: database/migrations/2026_03_24_000001_create_subjects_table.php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('subjects', function (Blueprint $table) {
            $table->id();
            $table->string('name');
            $table->string('code', 50)->unique();
            $table->text('description')->nullable();
            $table->string('icon_url', 500)->nullable();
            $table->integer('sort_order')->default(0);
            $table->boolean('is_active')->default(true)->index();
            $table->timestamps();
            $table->softDeletes();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('subjects');
    }
};


// File: database/migrations/2026_03_24_000002_create_topics_table.php

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('topics', function (Blueprint $table) {
            $table->id();
            $table->foreignId('subject_id')->constrained()->cascadeOnDelete();
            $table->foreignId('parent_topic_id')->nullable()
                  ->constrained('topics')->nullOnDelete();
            $table->string('name');
            $table->string('code', 100)->unique();
            $table->unsignedTinyInteger('depth')->default(0);
            $table->integer('sort_order')->default(0);
            $table->boolean('is_active')->default(true);
            $table->timestamps();
            $table->softDeletes();

            $table->index(['subject_id', 'is_active']);
            $table->index('parent_topic_id');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('topics');
    }
};


// File: database/migrations/2026_03_24_000003_create_tags_table.php

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('tags', function (Blueprint $table) {
            $table->id();
            $table->string('name', 100);
            $table->string('slug', 100)->unique();
            $table->string('category', 50)->nullable()->index();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('tags');
    }
};


// File: database/migrations/2026_03_24_000004_create_questions_table.php

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
            $table->foreignId('import_batch_id')->nullable();
            $table->string('external_id', 255)->nullable();

            // Denormalized stats
            $table->unsignedInteger('times_used')->default(0);
            $table->unsignedInteger('times_correct')->default(0);
            $table->unsignedInteger('times_incorrect')->default(0);
            $table->decimal('avg_time_sec', 8, 2)->nullable();

            $table->timestamps();
            $table->softDeletes();

            // ── INDEXES (critical for 100k+ rows) ──
            $table->index('subject_id');
            $table->index('topic_id');
            $table->index('type');
            $table->index('difficulty');
            $table->index('status');
            $table->index('import_batch_id');
            $table->index('created_at');

            // Composite index for common filter combos
            $table->index(['subject_id', 'topic_id', 'type', 'difficulty', 'status'], 'idx_questions_filter');

            // Full-text search (MySQL 8.0+ InnoDB supports FULLTEXT)
            $table->fullText('question_text');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('questions');
    }
};


// File: database/migrations/2026_03_24_000005_create_question_options_table.php

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('question_options', function (Blueprint $table) {
            $table->id();
            $table->foreignId('question_id')->constrained()->cascadeOnDelete();
            $table->text('option_text');
            $table->json('option_media')->nullable();
            $table->boolean('is_correct')->default(false);
            $table->unsignedTinyInteger('sort_order')->default(0);
            $table->text('explanation')->nullable();
            $table->timestamps();

            $table->index('question_id');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('question_options');
    }
};


// File: database/migrations/2026_03_24_000006_create_question_blanks_table.php

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('question_blanks', function (Blueprint $table) {
            $table->id();
            $table->foreignId('question_id')->constrained()->cascadeOnDelete();
            $table->unsignedTinyInteger('blank_number');
            $table->json('correct_answers');  // ["Jupiter","jupiter"]
            $table->boolean('is_case_sensitive')->default(false);
            $table->timestamps();

            $table->unique(['question_id', 'blank_number']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('question_blanks');
    }
};


// File: database/migrations/2026_03_24_000007_create_question_match_pairs_table.php

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('question_match_pairs', function (Blueprint $table) {
            $table->id();
            $table->foreignId('question_id')->constrained()->cascadeOnDelete();
            $table->text('column_a_text');
            $table->json('column_a_media')->nullable();
            $table->text('column_b_text');
            $table->json('column_b_media')->nullable();
            $table->unsignedTinyInteger('sort_order')->default(0);
            $table->timestamps();

            $table->index('question_id');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('question_match_pairs');
    }
};


// File: database/migrations/2026_03_24_000008_create_question_expected_answers_table.php

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('question_expected_answers', function (Blueprint $table) {
            $table->id();
            $table->foreignId('question_id')->constrained()->cascadeOnDelete();
            $table->text('answer_text');
            $table->json('keywords')->nullable();      // ["photosynthesis","chlorophyll"]
            $table->unsignedInteger('min_words')->nullable();
            $table->unsignedInteger('max_words')->nullable();
            $table->json('rubric')->nullable();
            $table->timestamps();

            $table->index('question_id');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('question_expected_answers');
    }
};


// File: database/migrations/2026_03_24_000009_create_question_tags_table.php

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('question_tags', function (Blueprint $table) {
            $table->foreignId('question_id')->constrained()->cascadeOnDelete();
            $table->foreignId('tag_id')->constrained()->cascadeOnDelete();
            $table->primary(['question_id', 'tag_id']);

            $table->index('tag_id');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('question_tags');
    }
};


// File: database/migrations/2026_03_24_000010_create_import_batches_table.php

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('import_batches', function (Blueprint $table) {
            $table->id();
            $table->string('file_name', 500);
            $table->string('file_path', 1000)->nullable();
            $table->unsignedBigInteger('file_size_bytes')->nullable();

            $table->enum('status', [
                'pending', 'validating', 'processing',
                'completed', 'failed', 'partial'
            ])->default('pending');

            $table->unsignedInteger('total_rows')->default(0);
            $table->unsignedInteger('processed_rows')->default(0);
            $table->unsignedInteger('success_count')->default(0);
            $table->unsignedInteger('error_count')->default(0);

            $table->json('error_log')->nullable();
            $table->json('summary')->nullable();

            $table->foreignId('imported_by')->constrained('users');
            $table->timestamp('started_at')->nullable();
            $table->timestamp('completed_at')->nullable();
            $table->timestamps();

            $table->index('status');
            $table->index('imported_by');
        });

        // Add foreign key to questions table
        Schema::table('questions', function (Blueprint $table) {
            $table->foreign('import_batch_id')
                  ->references('id')
                  ->on('import_batches')
                  ->nullOnDelete();
        });
    }

    public function down(): void
    {
        Schema::table('questions', function (Blueprint $table) {
            $table->dropForeign(['import_batch_id']);
        });
        Schema::dropIfExists('import_batches');
    }
};


// File: database/migrations/2026_03_24_000011_create_question_audit_logs_table.php

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('question_audit_logs', function (Blueprint $table) {
            $table->id();
            $table->foreignId('question_id')->constrained()->cascadeOnDelete();
            $table->string('action', 50);    // created, updated, approved, archived
            $table->json('changed_fields')->nullable();
            $table->foreignId('performed_by')->constrained('users');
            $table->timestamps();

            $table->index('question_id');
            $table->index('created_at');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('question_audit_logs');
    }
};