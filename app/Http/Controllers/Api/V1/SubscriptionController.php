<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Models\{Plan, UserSubscription};
use Illuminate\Http\Request;
use Illuminate\Http\JsonResponse;

class SubscriptionController extends Controller
{
    /**
     * GET /api/v1/my/subscription
     * Current user's active subscription.
     */
    public function myCurrent(Request $request): JsonResponse
    {
        $sub = $request->user()->activeSubscription();

        if (!$sub) {
            return response()->json(['data' => null, 'message' => 'No active subscription.']);
        }

        return response()->json([
            'data' => $this->formatSub($sub->load('plan')),
        ]);
    }

    /**
     * GET /api/v1/my/subscriptions
     * Full subscription history for current user.
     */
    public function myHistory(Request $request): JsonResponse
    {
        $subs = $request->user()
            ->subscriptions()
            ->with('plan:id,name,slug,billing_cycle,price')
            ->latest()
            ->paginate(20);

        return response()->json($subs);
    }

    // ── User: Subscribe ───────────────────────────────────────────────

    /**
     * POST /api/v1/plans/{plan}/subscribe
     *
     * Logged-in user subscribes to a plan.
     * - Free plans → activated immediately.
     * - Paid plans → created as 'pending'; admin activates after payment confirmation.
     *
     * Body (paid plans): { "payment_method": "upi|bank_transfer|card|other", "payment_reference": "TXN123" }
     */
    public function subscribe(Request $request, Plan $plan): JsonResponse
    {
        $user = $request->user();

        if (!$plan->is_active) {
            return response()->json(['message' => 'This plan is no longer available.'], 422);
        }

        // Block if user already has an active subscription for this plan
        $existing = UserSubscription::where('user_id', $user->id)
            ->where('plan_id', $plan->id)
            ->where('status', 'active')
            ->where(function ($q) {
                $q->whereNull('expires_at')->orWhere('expires_at', '>', now());
            })
            ->first();

        if ($existing) {
            return response()->json([
                'message'  => 'You already have an active subscription for this plan.',
                'data'     => $this->formatSub($existing->load('plan')),
            ], 422);
        }

        $isFree = $plan->isFree();

        $data = $request->validate([
            'payment_method'    => $isFree ? 'nullable' : 'required|string|in:upi,bank_transfer,card,cash,other',
            'payment_reference' => $isFree ? 'nullable' : 'required|string|max:255',
        ]);

        $startsAt  = now();
        $expiresAt = $plan->isLifetime() ? null : $startsAt->copy()->addDays($plan->duration_days);

        $sub = UserSubscription::create([
            'user_id'           => $user->id,
            'plan_id'           => $plan->id,
            'status'            => $isFree ? 'active' : 'pending',
            'starts_at'         => $startsAt,
            'expires_at'        => $expiresAt,
            'payment_method'    => $isFree ? 'free' : $data['payment_method'],
            'payment_reference' => $data['payment_reference'] ?? null,
            'amount_paid'       => $isFree ? 0 : $plan->price,
        ]);

        $message = $isFree
            ? 'You have successfully subscribed to the free plan.'
            : 'Subscription request submitted. It will be activated once your payment is verified.';

        return response()->json([
            'message' => $message,
            'data'    => $this->formatSub($sub->load('plan')),
        ], 201);
    }

    /**
     * POST /api/v1/my/subscription/cancel
     * Cancel the current user's active subscription.
     */
    public function cancel(Request $request): JsonResponse
    {
        $sub = $request->user()->activeSubscription();

        if (!$sub) {
            return response()->json(['message' => 'No active subscription to cancel.'], 422);
        }

        $sub->update(['status' => 'cancelled']);

        return response()->json(['message' => 'Subscription cancelled successfully.']);
    }

    // ── Admin endpoints ────────────────────────────────────────────────

    /**
     * GET /api/v1/admin/subscriptions
     * All subscriptions with optional filters.
     */
    public function adminIndex(Request $request): JsonResponse
    {
        $subs = UserSubscription::with(['user:id,name,email', 'plan:id,name,slug'])
            ->when($request->status,  fn($q, $s) => $q->where('status', $s))
            ->when($request->plan_id, fn($q, $p) => $q->where('plan_id', $p))
            ->when($request->user_id, fn($q, $u) => $q->where('user_id', $u))
            ->latest()
            ->paginate(25);

        return response()->json($subs);
    }

    /**
     * POST /api/v1/admin/subscriptions
     * Manually assign a plan to a user (e.g. after offline payment).
     */
    public function adminStore(Request $request): JsonResponse
    {
        $data = $request->validate([
            'user_id'           => 'required|exists:users,id',
            'plan_id'           => 'required|exists:plans,id',
            'status'            => 'nullable|in:active,pending',
            'starts_at'         => 'nullable|date',
            'payment_reference' => 'nullable|string|max:255',
            'payment_method'    => 'nullable|string|max:50',
            'amount_paid'       => 'nullable|numeric|min:0',
            'notes'             => 'nullable|string',
        ]);

        $plan = Plan::findOrFail($data['plan_id']);

        $startsAt  = isset($data['starts_at']) ? \Carbon\Carbon::parse($data['starts_at']) : now();
        $expiresAt = $plan->isLifetime()
            ? null
            : $startsAt->copy()->addDays($plan->duration_days);

        $sub = UserSubscription::create([
            'user_id'           => $data['user_id'],
            'plan_id'           => $data['plan_id'],
            'status'            => $data['status'] ?? 'active',
            'starts_at'         => $startsAt,
            'expires_at'        => $expiresAt,
            'payment_reference' => $data['payment_reference'] ?? null,
            'payment_method'    => $data['payment_method'] ?? 'manual',
            'amount_paid'       => $data['amount_paid'] ?? $plan->price,
            'notes'             => $data['notes'] ?? null,
        ]);

        return response()->json([
            'message' => 'Subscription created.',
            'data'    => $this->formatSub($sub->load('plan', 'user:id,name,email')),
        ], 201);
    }

    /**
     * PATCH /api/v1/admin/subscriptions/{subscription}/status
     * Change subscription status (activate / cancel / expire).
     */
    public function updateStatus(Request $request, UserSubscription $subscription): JsonResponse
    {
        $data = $request->validate([
            'status' => 'required|in:active,expired,cancelled,pending',
            'notes'  => 'nullable|string',
        ]);

        $subscription->update($data);

        return response()->json([
            'message' => 'Subscription status updated.',
            'data'    => $this->formatSub($subscription->load('plan', 'user:id,name,email')),
        ]);
    }

    /**
     * PATCH /api/v1/admin/subscriptions/{subscription}/extend
     * Extend expiry by N days.
     */
    public function extend(Request $request, UserSubscription $subscription): JsonResponse
    {
        $data = $request->validate([
            'days'  => 'required|integer|min:1|max:3650',
            'notes' => 'nullable|string',
        ]);

        $base = ($subscription->expires_at && $subscription->expires_at->isFuture())
            ? $subscription->expires_at
            : now();

        $subscription->update([
            'expires_at' => $base->addDays($data['days']),
            'status'     => 'active',
            'notes'      => $data['notes'] ?? $subscription->notes,
        ]);

        return response()->json([
            'message' => "Subscription extended by {$data['days']} days.",
            'data'    => $this->formatSub($subscription->fresh()->load('plan', 'user:id,name,email')),
        ]);
    }

    // ── Helper ──

    private function formatSub(UserSubscription $sub): array
    {
        return [
            'id'                => $sub->id,
            'status'            => $sub->status,
            'starts_at'         => $sub->starts_at?->toDateString(),
            'expires_at'        => $sub->expires_at?->toDateString(),
            'is_active'         => $sub->isActive(),
            'days_remaining'    => $sub->daysRemaining(),
            'payment_reference' => $sub->payment_reference,
            'payment_method'    => $sub->payment_method,
            'amount_paid'       => $sub->amount_paid,
            'notes'             => $sub->notes,
            'plan'              => $sub->relationLoaded('plan') ? $sub->plan : null,
            'user'              => $sub->relationLoaded('user') ? $sub->user : null,
        ];
    }
}
