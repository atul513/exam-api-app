
// ─────────────────────────────────────────────────────────────
// FILE: database/migrations/2026_04_01_000002_create_invitations_table.php
// Personal invites (one per person per content)
// ─────────────────────────────────────────────────────────────
<?php
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('invitations', function (Blueprint $table) {
            $table->id();
            $table->string('invite_code', 32)->unique();

            // Polymorphic content
            $table->string('invitable_type');
            $table->unsignedBigInteger('invitable_id');

            // Recipient info
            $table->string('recipient_name')->nullable();
            $table->string('recipient_email')->nullable();
            $table->string('recipient_phone', 20)->nullable();

            // If recipient registered, link to user
            $table->foreignId('recipient_user_id')->nullable()->constrained('users')->nullOnDelete();

            // Custom message
            $table->text('message')->nullable();

            // Delivery channels
            $table->json('sent_via')->nullable();
            // ["email", "whatsapp", "sms"]

            // Tracking
            $table->enum('status', [
                'pending',      // invite created, not sent yet
                'sent',         // sent via at least one channel
                'delivered',    // delivery confirmed (email/SMS callback)
                'opened',       // recipient clicked the link
                'registered',   // recipient completed registration
                'attempted',    // recipient started the quiz
                'completed',    // recipient finished the quiz
                'expired',      // invite expired
                'cancelled',    // admin cancelled
            ])->default('pending');

            $table->timestamp('sent_at')->nullable();
            $table->timestamp('opened_at')->nullable();
            $table->timestamp('registered_at')->nullable();
            $table->timestamp('attempted_at')->nullable();
            $table->timestamp('completed_at')->nullable();
            $table->timestamp('expires_at')->nullable();

            $table->foreignId('invited_by')->constrained('users');
            $table->timestamps();

            $table->index(['invitable_type', 'invitable_id']);
            $table->index('recipient_email');
            $table->index('recipient_phone');
            $table->index('status');
            $table->index('invited_by');
            $table->unique(['invitable_type', 'invitable_id', 'recipient_email'], 'inv_email_unique');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('invitations');
    }
};
