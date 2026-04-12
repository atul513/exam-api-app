<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('quizzes', function (Blueprint $table) {
            $table->foreignId('exam_section_id')->nullable()
                  ->after('category_id')
                  ->constrained('exam_sections')->nullOnDelete();
            $table->index('exam_section_id');
        });

        Schema::table('practice_sets', function (Blueprint $table) {
            $table->foreignId('exam_section_id')->nullable()
                  ->after('category_id')
                  ->constrained('exam_sections')->nullOnDelete();
            $table->index('exam_section_id');
        });

        Schema::table('questions', function (Blueprint $table) {
            $table->foreignId('exam_section_id')->nullable()
                  ->after('topic_id')
                  ->constrained('exam_sections')->nullOnDelete();
            $table->index('exam_section_id');
        });
    }

    public function down(): void
    {
        foreach (['quizzes', 'practice_sets', 'questions'] as $tbl) {
            Schema::table($tbl, function (Blueprint $t) {
                $t->dropConstrainedForeignId('exam_section_id');
            });
        }
    }
};
