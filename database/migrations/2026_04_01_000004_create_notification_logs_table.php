<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

// ─────────────────────────────────────────────────────────────
// FILE: database/migrations/2026_04_01_000004_create_notification_logs_table.php
// Tracks all sent notifications (email, WhatsApp, SMS)
// ─────────────────────────────────────────────────────────────

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('notification_logs', function (Blueprint $table) {
            $table->id();
            $table->foreignId('invitation_id')->nullable()->constrained()->nullOnDelete();
            $table->enum('channel', ['email', 'whatsapp', 'sms']);
            $table->string('recipient');            // email address or phone number
            $table->string('subject')->nullable();  // email subject
            $table->text('body')->nullable();        // message content sent
            $table->enum('status', ['queued', 'sent', 'delivered', 'failed', 'bounced'])->default('queued');
            $table->text('error_message')->nullable();
            $table->string('external_id')->nullable(); // provider message ID
            $table->timestamp('sent_at')->nullable();
            $table->timestamp('delivered_at')->nullable();
            $table->timestamps();

            $table->index('invitation_id');
            $table->index(['channel', 'status']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('notification_logs');
    }
};
