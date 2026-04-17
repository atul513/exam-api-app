<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

// ─────────────────────────────────────────────────────────────
// FILE: database/migrations/2026_04_01_000003_create_share_link_clicks_table.php
// Track every click on share/invite links
// ─────────────────────────────────────────────────────────────

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('share_link_clicks', function (Blueprint $table) {
            $table->id();

            // Either share_link or invitation (one will be null)
            $table->foreignId('share_link_id')->nullable()->constrained()->nullOnDelete();
            $table->foreignId('invitation_id')->nullable()->constrained()->nullOnDelete();

            $table->foreignId('user_id')->nullable()->constrained()->nullOnDelete();
            $table->string('ip_address', 45)->nullable();
            $table->string('user_agent', 500)->nullable();
            $table->string('referer', 500)->nullable();
            $table->string('source', 50)->nullable();  // email, whatsapp, sms, direct
            $table->timestamps();

            $table->index('share_link_id');
            $table->index('invitation_id');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('share_link_clicks');
    }
};
