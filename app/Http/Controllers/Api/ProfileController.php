<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\Storage;
use Illuminate\Validation\Rule;

class ProfileController extends Controller
{
    /**
     * GET /api/profile
     * Return current user's profile.
     */
    public function show(Request $request): JsonResponse
    {
        return response()->json([
            'data' => $this->formatUser($request->user()),
        ]);
    }

    /**
     * PUT /api/profile
     * Update personal info + address.
     */
    public function update(Request $request): JsonResponse
    {
        $user = $request->user();

        $data = $request->validate([
            // Personal info
            'first_name'  => 'nullable|string|max:100',
            'last_name'   => 'nullable|string|max:100',
            'username'    => ['nullable', 'string', 'max:50', 'alpha_dash', Rule::unique('users', 'username')->ignore($user->id)],
            'email'       => ['nullable', 'email', Rule::unique('users', 'email')->ignore($user->id)],
            'phone_code'  => 'nullable|string|max:10',
            'phone'       => 'nullable|string|max:20',
            // Address info
            'country'     => 'nullable|string|max:100',
            'address'     => 'nullable|string|max:255',
            'city'        => 'nullable|string|max:100',
            'postal_code' => 'nullable|string|max:20',
        ]);

        // Keep name in sync with first+last for backwards compat
        if (isset($data['first_name']) || isset($data['last_name'])) {
            $first = $data['first_name'] ?? $user->first_name;
            $last  = $data['last_name']  ?? $user->last_name;
            $data['name'] = trim("{$first} {$last}") ?: $user->name;
        }

        $user->update($data);

        return response()->json([
            'message' => 'Profile updated.',
            'data'    => $this->formatUser($user->fresh()),
        ]);
    }

    /**
     * POST /api/profile/avatar
     * Upload profile image.
     */
    public function uploadAvatar(Request $request): JsonResponse
    {
        $request->validate([
            'avatar' => 'required|image|mimes:jpg,jpeg,png,webp|max:2048',
        ]);

        $user = $request->user();

        // Delete old avatar
        if ($user->avatar) {
            Storage::disk('public')->delete($user->avatar);
        }

        $path = $request->file('avatar')->store('avatars', 'public');
        $user->update(['avatar' => $path]);

        return response()->json([
            'message'    => 'Avatar uploaded.',
            'avatar_url' => asset('storage/' . $path),
        ]);
    }

    /**
     * DELETE /api/profile/avatar
     * Remove profile image.
     */
    public function removeAvatar(Request $request): JsonResponse
    {
        $user = $request->user();

        if ($user->avatar) {
            Storage::disk('public')->delete($user->avatar);
            $user->update(['avatar' => null]);
        }

        return response()->json(['message' => 'Avatar removed.']);
    }

    // ── HELPER ──

    private function formatUser($user): array
    {
        return [
            'id'          => $user->id,
            'name'        => $user->name,
            'first_name'  => $user->first_name,
            'last_name'   => $user->last_name,
            'username'    => $user->username,
            'email'       => $user->email,
            'phone_code'  => $user->phone_code,
            'phone'       => $user->phone,
            'avatar_url'  => $user->avatar_url,
            'country'     => $user->country,
            'address'     => $user->address,
            'city'        => $user->city,
            'postal_code' => $user->postal_code,
            'role'        => $user->role,
        ];
    }
}
