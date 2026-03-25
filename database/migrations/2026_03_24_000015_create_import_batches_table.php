<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

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

        // Add foreign key to questions now that import_batches exists
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