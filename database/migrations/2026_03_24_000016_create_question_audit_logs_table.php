<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('question_audit_logs', function (Blueprint $table) {
            $table->id();
            $table->foreignId('question_id')->constrained()->cascadeOnDelete();
            $table->string('action', 50);
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