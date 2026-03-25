<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('question_blanks', function (Blueprint $table) {
            $table->id();
            $table->foreignId('question_id')->constrained()->cascadeOnDelete();
            $table->unsignedTinyInteger('blank_number');
            $table->json('correct_answers');
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