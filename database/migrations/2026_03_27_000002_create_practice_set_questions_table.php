<?php
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('practice_set_questions', function (Blueprint $table) {
            $table->id();
            $table->foreignId('practice_set_id')->constrained()->cascadeOnDelete();
            $table->foreignId('question_id')->constrained()->cascadeOnDelete();
            $table->unsignedInteger('sort_order')->default(0);
            $table->unsignedInteger('points_override')->nullable();
            // null = use practice_set.points_per_question or question.marks
            $table->timestamps();

            $table->unique(['practice_set_id', 'question_id']);
            $table->index(['practice_set_id', 'sort_order']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('practice_set_questions');
    }
};

