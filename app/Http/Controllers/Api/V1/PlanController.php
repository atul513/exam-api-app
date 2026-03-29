<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Models\Plan;
use Illuminate\Http\Request;
use Illuminate\Http\JsonResponse;

class PlanController extends Controller
{
    /**
     * GET /api/v1/plans
     * Public list of active plans.
     */
    public function index(): JsonResponse
    {
        $plans = Plan::where('is_active', true)
            ->orderBy('sort_order')
            ->orderBy('price')
            ->get();

        return response()->json(['data' => $plans]);
    }

    /**
     * GET /api/v1/plans/{plan}
     * Single plan detail (public).
     */
    public function show(Plan $plan): JsonResponse
    {
        return response()->json(['data' => $plan]);
    }

    // ── Admin endpoints ────────────────────────────────────────────────

    /**
     * GET /api/v1/admin/plans
     */
    public function adminIndex(): JsonResponse
    {
        $plans = Plan::withCount(['subscriptions', 'activeSubscriptions'])
            ->orderBy('sort_order')
            ->get();

        return response()->json(['data' => $plans]);
    }

    /**
     * POST /api/v1/admin/plans
     */
    public function store(Request $request): JsonResponse
    {
        $data = $request->validate([
            'name'          => 'required|string|max:100',
            'slug'          => 'nullable|string|max:100|unique:plans,slug',
            'description'   => 'nullable|string',
            'price'         => 'required|numeric|min:0',
            'billing_cycle' => 'nullable|in:monthly,yearly,lifetime,one_time',
            'duration_days' => 'nullable|integer|min:1',
            'features'      => 'nullable|array',
            'features.*'    => 'string|max:255',
            'sort_order'    => 'nullable|integer|min:0',
            'is_active'     => 'nullable|boolean',
            'is_featured'   => 'nullable|boolean',
        ]);

        $data['billing_cycle'] = $data['billing_cycle'] ?? 'monthly';

        $data['duration_days'] = $data['duration_days'] ?? match($data['billing_cycle']) {
            'monthly'  => 30,
            'yearly'   => 365,
            'lifetime' => 36500,
            'one_time' => 30,
        };

        $plan = Plan::create($data);

        return response()->json(['message' => 'Plan created.', 'data' => $plan], 201);
    }

    /**
     * PUT /api/v1/admin/plans/{plan}
     */
    public function update(Request $request, Plan $plan): JsonResponse
    {
        $data = $request->validate([
            'name'          => 'sometimes|string|max:100',
            'slug'          => "sometimes|string|max:100|unique:plans,slug,{$plan->id}",
            'description'   => 'nullable|string',
            'price'         => 'sometimes|numeric|min:0',
            'billing_cycle' => 'sometimes|in:monthly,yearly,lifetime,one_time',
            'duration_days' => 'sometimes|integer|min:1',
            'features'      => 'nullable|array',
            'features.*'    => 'string|max:255',
            'sort_order'    => 'nullable|integer|min:0',
            'is_active'     => 'nullable|boolean',
            'is_featured'   => 'nullable|boolean',
        ]);

        $plan->update($data);

        return response()->json(['message' => 'Plan updated.', 'data' => $plan->fresh()]);
    }

    /**
     * DELETE /api/v1/admin/plans/{plan}
     */
    public function destroy(Plan $plan): JsonResponse
    {
        if ($plan->activeSubscriptions()->exists()) {
            return response()->json([
                'message' => 'Cannot delete a plan with active subscriptions.',
            ], 422);
        }

        $plan->delete();

        return response()->json(['message' => 'Plan deleted.']);
    }
}
