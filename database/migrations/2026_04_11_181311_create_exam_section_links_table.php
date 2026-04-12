<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('exam_section_links', function (Blueprint $table) {
            $table->id();
            $table->foreignId('exam_section_id')->constrained()->cascadeOnDelete();
            $table->string('linkable_type');
            $table->unsignedBigInteger('linkable_id');
            $table->integer('sort_order')->default(0);
            $table->timestamps();

            $table->unique(['exam_section_id', 'linkable_type', 'linkable_id'], 'esl_unique');
            $table->index(['linkable_type', 'linkable_id']);
            $table->index('exam_section_id');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('exam_section_links');
    }
};
