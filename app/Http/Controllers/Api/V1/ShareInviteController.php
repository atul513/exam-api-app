<?php

// ─────────────────────────────────────────────────────────────
// FILE: app/Http/Controllers/Api/V1/ShareInviteController.php
// ─────────────────────────────────────────────────────────────

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Models\{ShareLink, Invitation, User};
use App\Services\ShareInviteService;
use Illuminate\Http\Request;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\Hash;

class ShareInviteController extends Controller
{
    public function __construct(private ShareInviteService $service) {}

    /**
     * POST /api/v1/share/create-link
     * Create or get public share link for a quiz/practice_set/coding_test.
     */
    public function createShareLink(Request $request): JsonResponse
    {
        $data = $request->validate([
            'content_type'         => 'required|in:quiz,practice_set,coding_test',
            'content_id'           => 'required|integer',
            'title'                => 'nullable|string|max:255',
            'message'              => 'nullable|string|max:2000',
            'require_registration' => 'nullable|boolean',
            'expires_at'           => 'nullable|date|after:now',
            'max_registrations'    => 'nullable|integer|min:1',
        ]);

        $link = $this->service->createShareLink(
            $data['content_type'],
            $data['content_id'],
            $request->user()->id,
            $data
        );

        return response()->json([
            'message' => 'Share link ready.',
            'data'    => [
                'share_code' => $link->share_code,
                'url'        => $link->getFullUrl(),
                'copy_text'  => $this->buildShareText($link),
                'whatsapp_url' => $this->buildWhatsAppShareUrl($link),
            ],
        ]);
    }

    /**
     * POST /api/v1/share/send-invites
     * Send personal invites to multiple recipients.
     */
    public function sendInvites(Request $request): JsonResponse
    {
        $data = $request->validate([
            'content_type'         => 'required|in:quiz,practice_set,coding_test',
            'content_id'           => 'required|integer',
            'channels'             => 'required|array|min:1',
            'channels.*'           => 'in:email,whatsapp,sms',
            'message'              => 'nullable|string|max:2000',
            'recipients'           => 'required|array|min:1|max:500',
            'recipients.*.name'    => 'nullable|string|max:255',
            'recipients.*.email'   => 'nullable|email|required_without:recipients.*.phone',
            'recipients.*.phone'   => 'nullable|string|max:20|required_without:recipients.*.email',
            'recipients.*.expires_at' => 'nullable|date|after:now',
        ]);

        $results = $this->service->sendInvites(
            $data['content_type'],
            $data['content_id'],
            $request->user()->id,
            $data['recipients'],
            $data['channels'],
            $data['message'] ?? null
        );

        return response()->json([
            'message' => "{$results['sent']} invite(s) sent, {$results['skipped']} skipped.",
            'data'    => $results,
        ]);
    }

    /**
     * GET /api/v1/share/analytics/{contentType}/{contentId}
     * Full funnel analytics: invited → opened → registered → attempted → completed.
     */
    public function analytics(string $contentType, int $contentId): JsonResponse
    {
        $data = $this->service->getAnalytics($contentType, $contentId);
        return response()->json(['data' => $data]);
    }

    /**
     * GET /api/v1/share/invitations/{contentType}/{contentId}
     * List all invitations for a content with status.
     */
    public function listInvitations(Request $request, string $contentType, int $contentId): JsonResponse
    {
        $modelMap = [
            'quiz'         => \App\Models\Quiz::class,
            'practice_set' => \App\Models\PracticeSet::class,
            'coding_test'  => \App\Models\CodingTest::class,
        ];

        $invitations = Invitation::where('invitable_type', $modelMap[$contentType] ?? '')
            ->where('invitable_id', $contentId)
            ->with('recipientUser:id,name,email')
            ->when($request->status, fn($q, $s) => $q->where('status', $s))
            ->orderByDesc('created_at')
            ->paginate(50);

        return response()->json($invitations);
    }

    /**
     * POST /api/v1/share/resend/{invitation}
     */
    public function resend(Invitation $invitation): JsonResponse
    {
        if (!$invitation->isValid()) {
            return response()->json(['message' => 'Invitation is expired or cancelled.'], 422);
        }

        $content = $invitation->invitable;
        $channels = $invitation->sent_via ?? ['email'];
        $inviteUrl = $invitation->getFullUrl();
        $title = $content->title ?? 'Assessment';

        foreach ($channels as $channel) {
            match ($channel) {
                'email'    => $invitation->recipient_email ? \App\Jobs\SendInviteEmail::dispatch($invitation, $title, $inviteUrl) : null,
                'whatsapp' => $invitation->recipient_phone ? \App\Jobs\SendInviteWhatsApp::dispatch($invitation, $title, $inviteUrl) : null,
                'sms'      => $invitation->recipient_phone ? \App\Jobs\SendInviteSms::dispatch($invitation, $title, $inviteUrl) : null,
                default    => null,
            };
        }

        $invitation->update(['sent_at' => now(), 'status' => 'sent']);

        return response()->json(['message' => 'Invite resent.']);
    }

    /**
     * POST /api/v1/share/cancel/{invitation}
     */
    public function cancel(Invitation $invitation): JsonResponse
    {
        $invitation->update(['status' => 'cancelled']);
        return response()->json(['message' => 'Invitation cancelled.']);
    }

    /**
     * PUT /api/v1/share/link/{shareLink}
     */
    public function updateShareLink(Request $request, ShareLink $shareLink): JsonResponse
    {
        $data = $request->validate([
            'title'              => 'nullable|string|max:255',
            'message'            => 'nullable|string|max:2000',
            'is_active'          => 'nullable|boolean',
            'expires_at'         => 'nullable|date',
            'max_registrations'  => 'nullable|integer|min:1',
        ]);

        $shareLink->update($data);
        return response()->json(['message' => 'Updated.', 'data' => $shareLink->fresh()]);
    }

    /**
     * DELETE /api/v1/share/link/{shareLink}
     */
    public function deactivateShareLink(ShareLink $shareLink): JsonResponse
    {
        $shareLink->update(['is_active' => false]);
        return response()->json(['message' => 'Share link deactivated.']);
    }

    // ── PUBLIC ENDPOINTS (no auth) ──

    /**
     * GET /api/v1/share/{shareCode}/resolve
     * Frontend calls this to get content info for the share page.
     */
    public function resolveShareLink(Request $request, string $shareCode): JsonResponse
    {
        $link = ShareLink::where('share_code', $shareCode)->firstOrFail();

        $this->service->trackClick($link->id, null, [
            'ip_address' => $request->ip(),
            'user_agent' => $request->userAgent(),
            'referer'    => $request->header('referer'),
            'source'     => $request->query('src', 'direct'),
        ]);

        if (!$link->isValid()) {
            return response()->json(['message' => 'This link has expired or is no longer active.', 'valid' => false], 410);
        }

        $content = $link->shareable;

        return response()->json([
            'valid'                => true,
            'require_registration' => $link->require_registration,
            'content' => [
                'type'        => class_basename($content),
                'id'          => $content->id,
                'title'       => $content->title,
                'description' => $content->description,
                'thumbnail'   => $link->thumbnail_url ?? $content->thumbnail_url,
            ],
            'share' => [
                'title'   => $link->title,
                'message' => $link->message,
            ],
        ]);
    }

    /**
     * GET /api/v1/invite/{inviteCode}/resolve
     * Frontend calls this to get invite info.
     */
    public function resolveInvite(Request $request, string $inviteCode): JsonResponse
    {
        $invite = Invitation::where('invite_code', $inviteCode)->firstOrFail();

        $this->service->trackClick(null, $invite->id, [
            'ip_address' => $request->ip(),
            'user_agent' => $request->userAgent(),
            'source'     => $request->query('src', 'direct'),
        ]);

        $invite->markOpened();

        if (!$invite->isValid()) {
            return response()->json(['message' => 'This invitation has expired or been cancelled.', 'valid' => false], 410);
        }

        $content = $invite->invitable;

        return response()->json([
            'valid'   => true,
            'content' => [
                'type'        => class_basename($content),
                'id'          => $content->id,
                'title'       => $content->title,
                'description' => $content->description,
            ],
            'invite' => [
                'recipient_name'  => $invite->recipient_name,
                'recipient_email' => $invite->recipient_email,
                'message'         => $invite->message,
                'invited_by'      => $invite->inviter->name ?? null,
            ],
            'already_registered' => $invite->recipient_user_id !== null,
        ]);
    }

    /**
     * POST /api/v1/invite/{inviteCode}/register
     * Full signup via invite link.
     */
    public function registerViaInvite(Request $request, string $inviteCode): JsonResponse
    {
        $invite = Invitation::where('invite_code', $inviteCode)->firstOrFail();

        if (!$invite->isValid()) {
            return response()->json(['message' => 'Invitation expired or cancelled.'], 410);
        }

        if ($invite->recipient_user_id) {
            return response()->json(['message' => 'Already registered. Please login.'], 422);
        }

        $data = $request->validate([
            'name'     => 'required|string|max:255',
            'email'    => 'required|email|unique:users,email',
            'phone'    => 'nullable|string|max:20',
            'password' => 'required|string|min:8|confirmed',
        ]);

        $user = User::create([
            'name'     => $data['name'],
            'email'    => $data['email'],
            'phone'    => $data['phone'] ?? $invite->recipient_phone,
            'password' => Hash::make($data['password']),
        ]);

        // Mark invite as registered
        $invite->markRegistered($user->id);

        // Update share link stats if applicable
        $this->service->handlePostRegistration($user, null, $inviteCode);

        // Create auth token
        $token = $user->createToken('invite-registration')->plainTextToken;

        return response()->json([
            'message'    => 'Registration successful.',
            'user'       => $user->only(['id', 'name', 'email']),
            'token'      => $token,
            'redirect_to' => [
                'type' => class_basename($invite->invitable),
                'id'   => $invite->invitable_id,
                'slug' => $invite->invitable->slug ?? null,
            ],
        ], 201);
    }

    // ── SHARE TEXT BUILDERS ──

    private function buildShareText(ShareLink $link): string
    {
        $title = $link->title ?? 'Assessment';
        $msg = $link->message ? "\n{$link->message}" : '';
        return "{$title}{$msg}\n\nAttempt here: {$link->getFullUrl()}";
    }

    private function buildWhatsAppShareUrl(ShareLink $link): string
    {
        $text = urlencode($this->buildShareText($link));
        return "https://wa.me/?text={$text}";
    }
}
