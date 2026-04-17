<?php

// ─────────────────────────────────────────────────────────────
// FILE: app/Jobs/SendInviteSms.php
// Placeholder — integrate with your SMS provider (Twilio, MSG91, etc.)
// ─────────────────────────────────────────────────────────────

namespace App\Jobs;

use App\Models\{Invitation, NotificationLog};
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\{InteractsWithQueue, SerializesModels};

class SendInviteSms implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public function __construct(
        private Invitation $invitation,
        private string $contentTitle,
        private string $inviteUrl,
    ) {}

    public function handle(): void
    {
        $log = NotificationLog::create([
            'invitation_id' => $this->invitation->id,
            'channel'       => 'sms',
            'recipient'     => $this->invitation->recipient_phone,
            'status'        => 'queued',
        ]);

        try {
            $message = "You're invited to: {$this->contentTitle}. Start here: {$this->inviteUrl}";

            // TODO: Replace with your SMS provider
            // Example with MSG91:
            //
            // Http::withHeaders(['authkey' => config('services.msg91.key')])
            //     ->post('https://api.msg91.com/api/v5/flow/', [
            //         'mobiles'   => $this->invitation->recipient_phone,
            //         'message'   => $message,
            //     ]);

            $log->update([
                'status' => 'sent',
                'sent_at' => now(),
                'body'   => $message,
            ]);

        } catch (\Throwable $e) {
            $log->update(['status' => 'failed', 'error_message' => $e->getMessage()]);
        }
    }
}
