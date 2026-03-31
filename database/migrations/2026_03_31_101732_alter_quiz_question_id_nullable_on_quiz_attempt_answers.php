<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::table('quiz_attempt_answers', function (Blueprint $table) {
            $table->dropForeign(['quiz_question_id']);
            $table->unsignedBigInteger('quiz_question_id')->nullable()->change();
            $table->foreign('quiz_question_id')
                  ->references('id')->on('quiz_questions')
                  ->nullOnDelete();
        });
    }

    public function down(): void
    {
        Schema::table('quiz_attempt_answers', function (Blueprint $table) {
            $table->dropForeign(['quiz_question_id']);
            $table->unsignedBigInteger('quiz_question_id')->nullable(false)->change();
            $table->foreign('quiz_question_id')
                  ->references('id')->on('quiz_questions');
        });
    }
};
