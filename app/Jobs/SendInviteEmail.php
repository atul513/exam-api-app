<?php

// ─────────────────────────────────────────────────────────────
// FILE: app/Jobs/SendInviteEmail.php
// ─────────────────────────────────────────────────────────────

namespace App\Jobs;

use App\Models\{Invitation, NotificationLog};
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\{InteractsWithQueue, SerializesModels};
use Illuminate\Support\Facades\Mail;

class SendInviteEmail implements ShouldQueue
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
            'channel'       => 'email',
            'recipient'     => $this->invitation->recipient_email,
            'subject'       => "You're invited: {$this->contentTitle}",
            'status'        => 'queued',
        ]);

        try {
            Mail::send([], [], function ($mail) {
                $name = $this->invitation->recipient_name ?? 'there';
                $inviter = $this->invitation->inviter->name ?? 'Someone';
                $message = $this->invitation->message ?? '';

                $body = "Hi {$name},\n\n"
                    . "{$inviter} has invited you to attempt: {$this->contentTitle}\n\n"
                    . ($message ? "Message: {$message}\n\n" : '')
                    . "Click here to start: {$this->inviteUrl}\n\n"
                    . "If you don't have an account, you'll be asked to register first.\n\n"
                    . "Good luck!";

                $mail->to($this->invitation->recipient_email, $this->invitation->recipient_name)
                     ->subject("You're invited: {$this->contentTitle}")
                     ->text($body);
            });

            $log->update(['status' => 'sent', 'sent_at' => now()]);

        } catch (\Throwable $e) {
            $log->update(['status' => 'failed', 'error_message' => $e->getMessage()]);
        }
    }
}
