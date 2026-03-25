<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

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