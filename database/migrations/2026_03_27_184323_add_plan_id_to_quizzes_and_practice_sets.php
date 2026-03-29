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
        // null = any active subscription can access (when access_type = paid)
        // set  = only subscribers of that specific plan can access
        Schema::table('quizzes', function (Blueprint $table) {
            $table->foreignId('plan_id')->nullable()->after('price')
                  ->constrained('plans')->nullOnDelete();
        });

        Schema::table('practice_sets', function (Blueprint $table) {
            $table->foreignId('plan_id')->nullable()->after('price')
                  ->constrained('plans')->nullOnDelete();
        });
    }

    public function down(): void
    {
        Schema::table('quizzes', function (Blueprint $table) {
            $table->dropForeign(['plan_id']);
            $table->dropColumn('plan_id');
        });

        Schema::table('practice_sets', function (Blueprint $table) {
            $table->dropForeign(['plan_id']);
            $table->dropColumn('plan_id');
        });
    }
};
