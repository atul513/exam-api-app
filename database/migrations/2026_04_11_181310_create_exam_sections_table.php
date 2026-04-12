<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('exam_sections', function (Blueprint $table) {
            $table->id();
            $table->foreignId('parent_id')->nullable()
                  ->constrained('exam_sections')->nullOnDelete();
            $table->string('name');
            $table->string('slug')->unique();
            $table->string('code', 50)->nullable()->unique();
            $table->string('type', 50);
            $table->text('description')->nullable();
            $table->string('short_name', 50)->nullable();
            $table->string('icon_url', 500)->nullable();
            $table->string('image_url', 500)->nullable();
            $table->unsignedTinyInteger('depth')->default(0);
            $table->json('meta')->nullable();
            $table->integer('sort_order')->default(0);
            $table->boolean('is_active')->default(true);
            $table->boolean('is_featured')->default(false);
            $table->timestamps();
            $table->softDeletes();

            $table->index('parent_id');
            $table->index('type');
            $table->index('is_active');
            $table->index('is_featured');
            $table->index(['type', 'is_active']);
            $table->index(['parent_id', 'sort_order']);
            $table->fullText('name');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('exam_sections');
    }
};
