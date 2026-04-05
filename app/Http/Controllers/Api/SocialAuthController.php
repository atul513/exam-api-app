<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\User;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Laravel\Socialite\Facades\Socialite;

class SocialAuthController extends Controller
{
    /**
     * GET /api/auth/google/redirect
     *
     * Returns the Google OAuth redirect URL.
     * The Vue frontend should redirect the user to this URL.
     */
    public function redirectToGoogle(): JsonResponse
    {
        $url = Socialite::driver('google')
            ->stateless()
            ->redirect()
            ->getTargetUrl();

        return response()->json(['url' => $url]);
    }

    /**
     * POST /api/auth/google/callback
     *
     * The Vue app gets the "code" from Google's redirect and POSTs it here.
     * Returns a Sanctum token on success.
     *
     * Body: { "code": "<google_authorization_code>" }
     */
    public function handleGoogleCallback(Request $request): JsonResponse
    {
        $request->validate([
            'code' => 'required|string',
        ]);

        try {
            $googleUser = Socialite::driver('google')
                ->stateless()
                ->getUser();
        } catch (\Exception $e) {
            return response()->json([
                'message' => 'Invalid or expired Google authorization code.',
            ], 422);
        }

        return $this->loginOrRegister($googleUser);
    }

    /**
     * POST /api/auth/google/token
     *
     * Alternative: Vue app uses Google JS SDK to get an access token
     * and POSTs it directly here.
     *
     * Body: { "access_token": "<google_access_token>" }
     */
    public function handleGoogleToken(Request $request): JsonResponse
    {
        $request->validate([
            'access_token' => 'required|string',
        ]);

        try {
            $googleUser = Socialite::driver('google')
                ->stateless()
                ->userFromToken($request->access_token);
        } catch (\Exception $e) {
            return response()->json([
                'message' => 'Invalid or expired Google access token.',
            ], 422);
        }

        return $this->loginOrRegister($googleUser);
    }

    /**
     * Find or create user from Google profile, return Sanctum token.
     */
    private function loginOrRegister(mixed $googleUser): JsonResponse
    {
        // Try to find by google_id first, then fall back to email
        $user = User::where('google_id', $googleUser->getId())->first()
            ?? User::where('email', $googleUser->getEmail())->first();

        if ($user) {
            // Sync google_id if this account was registered with email/password
            if (!$user->google_id) {
                $user->update(['google_id' => $googleUser->getId()]);
            }
        } else {
            // New user — register via Google
            $user = User::create([
                'name'      => $googleUser->getName(),
                'email'     => $googleUser->getEmail(),
                'google_id' => $googleUser->getId(),
                'avatar'    => $googleUser->getAvatar(),
                'role'      => User::ROLE_STUDENT,
                'email_verified_at' => now(),
            ]);
        }

        $token = $user->createToken('google_auth')->plainTextToken;

        return response()->json([
            'success' => true,
            'message' => 'Login successful',
            'token'   => $token,
            'user'    => $user,
        ]);
    }
}
