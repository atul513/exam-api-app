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
        Schema::table('users', function (Blueprint $table) {
            $table->string('first_name')->nullable()->after('name');
            $table->string('last_name')->nullable()->after('first_name');
            $table->string('username')->nullable()->unique()->after('last_name');
            $table->string('phone_code', 10)->nullable()->after('email');   // e.g. +91, +1
            $table->string('phone', 20)->nullable()->after('phone_code');
            $table->string('avatar')->nullable()->after('phone');
            $table->string('country', 100)->nullable()->after('avatar');
            $table->string('address')->nullable()->after('country');
            $table->string('city', 100)->nullable()->after('address');
            $table->string('postal_code', 20)->nullable()->after('city');
        });
    }

    public function down(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->dropColumn([
                'first_name', 'last_name', 'username',
                'phone_code', 'phone', 'avatar',
                'country', 'address', 'city', 'postal_code',
            ]);
        });
    }
};
