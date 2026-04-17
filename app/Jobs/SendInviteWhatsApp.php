<?php

// ─────────────────────────────────────────────────────────────
// FILE: app/Jobs/SendInviteWhatsApp.php
// Placeholder — integrate with your WhatsApp provider (Meta, Twilio, etc.)
// ─────────────────────────────────────────────────────────────

namespace App\Jobs;

use App\Models\{Invitation, NotificationLog};
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\{InteractsWithQueue, SerializesModels};

class SendInviteWhatsApp implements ShouldQueue
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
            'channel'       => 'whatsapp',
            'recipient'     => $this->invitation->recipient_phone,
            'status'        => 'queued',
        ]);

        try {
            $message = "Hi {$this->invitation->recipient_name}! "
                . "You've been invited to attempt: *{$this->contentTitle}*\n\n"
                . ($this->invitation->message ? "{$this->invitation->message}\n\n" : '')
                . "Start here: {$this->inviteUrl}";

            // TODO: Replace with your WhatsApp API integration
            // Example with Meta Cloud API:
            //
            // Http::withToken(config('services.whatsapp.token'))
            //     ->post('https://graph.facebook.com/v18.0/' . config('services.whatsapp.phone_id') . '/messages', [
            //         'messaging_product' => 'whatsapp',
            //         'to'   => $this->invitation->recipient_phone,
            //         'type' => 'text',
            //         'text' => ['body' => $message],
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

