<?php


// ─────────────────────────────────────────────────────────────
// FILE: app/Services/ShareInviteService.php
// ─────────────────────────────────────────────────────────────

namespace App\Services;

use App\Models\{ShareLink, Invitation, ShareLinkClick, NotificationLog, User};
use App\Jobs\{SendInviteEmail, SendInviteWhatsApp, SendInviteSms};
use Illuminate\Support\Facades\DB;

class ShareInviteService
{
    private array $modelMap = [
        'quiz'         => \App\Models\Quiz::class,
        'practice_set' => \App\Models\PracticeSet::class,
        'coding_test'  => \App\Models\CodingTest::class,
    ];

    /**
     * Create or get public share link.
     */
    public function createShareLink(string $contentType, int $contentId, int $userId, array $options = []): ShareLink
    {
        $model = $this->resolveModel($contentType, $contentId);
        return $model->getOrCreateShareLink($userId, $options);
    }

    /**
     * Send personal invites to multiple recipients.
     */
    public function sendInvites(string $contentType, int $contentId, int $invitedBy, array $recipients, array $channels, ?string $message = null): array
    {
        $modelClass = $this->modelMap[$contentType] ?? null;
        if (!$modelClass) throw new \Exception("Invalid content type: {$contentType}");

        $model = $modelClass::findOrFail($contentId);
        $results = ['sent' => 0, 'skipped' => 0, 'errors' => []];

        foreach ($recipients as $recipient) {
            $email = $recipient['email'] ?? null;
            $phone = $recipient['phone'] ?? null;
            $name = $recipient['name'] ?? null;

            // Skip if already invited (by email)
            if ($email) {
                $existing = Invitation::where('invitable_type', $modelClass)
                    ->where('invitable_id', $contentId)
                    ->where('recipient_email', $email)
                    ->whereNotIn('status', ['expired', 'cancelled'])
                    ->first();

                if ($existing) {
                    $results['skipped']++;
                    continue;
                }
            }

            try {
                $invitation = Invitation::create([
                    'invitable_type'  => $modelClass,
                    'invitable_id'    => $contentId,
                    'recipient_name'  => $name,
                    'recipient_email' => $email,
                    'recipient_phone' => $phone,
                    'message'         => $message,
                    'sent_via'        => $channels,
                    'status'          => 'pending',
                    'expires_at'      => $recipient['expires_at'] ?? null,
                    'invited_by'      => $invitedBy,
                ]);

                // Dispatch notification jobs per channel
                $this->dispatchNotifications($invitation, $channels, $model);

                $invitation->update(['status' => 'sent', 'sent_at' => now()]);
                $results['sent']++;

            } catch (\Throwable $e) {
                $results['errors'][] = [
                    'recipient' => $email ?? $phone,
                    'error'     => $e->getMessage(),
                ];
            }
        }

        return $results;
    }

    /**
     * Track a click on share link or invite.
     */
    public function trackClick(?int $shareLinkId, ?int $invitationId, array $meta = []): void
    {
        ShareLinkClick::create([
            'share_link_id' => $shareLinkId,
            'invitation_id' => $invitationId,
            'user_id'       => $meta['user_id'] ?? null,
            'ip_address'    => $meta['ip_address'] ?? null,
            'user_agent'    => $meta['user_agent'] ?? null,
            'referer'       => $meta['referer'] ?? null,
            'source'        => $meta['source'] ?? null,
        ]);

        if ($shareLinkId) {
            ShareLink::where('id', $shareLinkId)->increment('click_count');
        }

        if ($invitationId) {
            $invite = Invitation::find($invitationId);
            $invite?->markOpened();
        }
    }

    /**
     * Handle registration via share/invite link.
     * Called after user completes signup.
     */
    public function handlePostRegistration(User $user, ?string $shareCode, ?string $inviteCode): void
    {
        if ($shareCode) {
            $link = ShareLink::where('share_code', $shareCode)->first();
            if ($link) {
                $link->increment('registration_count');
            }
        }

        if ($inviteCode) {
            $invite = Invitation::where('invite_code', $inviteCode)->first();
            if ($invite) {
                $invite->markRegistered($user->id);
            }
        }
    }

    /**
     * Get analytics for a share link or all invites for a content.
     */
    public function getAnalytics(string $contentType, int $contentId): array
    {
        $modelClass = $this->modelMap[$contentType] ?? null;

        $shareLink = ShareLink::where('shareable_type', $modelClass)
            ->where('shareable_id', $contentId)
            ->where('is_active', true)
            ->first();

        $invitations = Invitation::where('invitable_type', $modelClass)
            ->where('invitable_id', $contentId)
            ->get();

        $statusCounts = $invitations->groupBy('status')
            ->map(fn($group) => $group->count());

        return [
            'share_link' => $shareLink ? [
                'url'                => $shareLink->getFullUrl(),
                'click_count'        => $shareLink->click_count,
                'registration_count' => $shareLink->registration_count,
                'attempt_count'      => $shareLink->attempt_count,
                'is_active'          => $shareLink->is_active,
                'expires_at'         => $shareLink->expires_at?->toISOString(),
            ] : null,

            'invitations' => [
                'total'     => $invitations->count(),
                'by_status' => $statusCounts,
                'channels'  => [
                    'email'    => $invitations->filter(fn($i) => in_array('email', $i->sent_via ?? []))->count(),
                    'whatsapp' => $invitations->filter(fn($i) => in_array('whatsapp', $i->sent_via ?? []))->count(),
                    'sms'      => $invitations->filter(fn($i) => in_array('sms', $i->sent_via ?? []))->count(),
                ],
            ],

            'funnel' => [
                'invited'    => $invitations->count(),
                'opened'     => $invitations->whereNotNull('opened_at')->count(),
                'registered' => $invitations->whereNotNull('registered_at')->count(),
                'attempted'  => $invitations->whereNotNull('attempted_at')->count(),
                'completed'  => $invitations->whereNotNull('completed_at')->count(),
            ],
        ];
    }

    // ── PRIVATE HELPERS ──

    private function resolveModel(string $type, int $id)
    {
        $class = $this->modelMap[$type] ?? null;
        if (!$class) throw new \Exception("Invalid content type: {$type}");
        return $class::findOrFail($id);
    }

    private function dispatchNotifications(Invitation $invitation, array $channels, $content): void
    {
        $inviteUrl = $invitation->getFullUrl();
        $contentTitle = $content->title ?? 'Assessment';

        foreach ($channels as $channel) {
            match ($channel) {
                'email'    => $invitation->recipient_email ? SendInviteEmail::dispatch($invitation, $contentTitle, $inviteUrl) : null,
                'whatsapp' => $invitation->recipient_phone ? SendInviteWhatsApp::dispatch($invitation, $contentTitle, $inviteUrl) : null,
                'sms'      => $invitation->recipient_phone ? SendInviteSms::dispatch($invitation, $contentTitle, $inviteUrl) : null,
                default    => null,
            };
        }
    }
}
