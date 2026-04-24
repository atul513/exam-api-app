<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\ContactSubmission;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Http;

class ContactController extends Controller
{
    /**
     * GET /api/contact-details
     * Public endpoint for contact page details.
     */
    public function details(): JsonResponse
    {
        return response()->json([
            'data' => [
                'email' => config('contact.email'),
                'phone' => config('contact.phone'),
                'phone_link' => config('contact.phone_link'),
                'address_lines' => config('contact.address_lines', []),
                'response_time' => config('contact.response_time'),
                'working_hours' => config('contact.working_hours'),
            ],
        ]);
    }
    /**
     * POST /api/contact
     * Public endpoint — no auth required.
     */
    public function store(Request $request): JsonResponse
    {
        $request->validate([
            'name'             => 'required|string|min:2|max:100',
            'email'            => 'required|email|max:255',
            'subject'          => 'required|string|min:3|max:200',
            'message'          => 'required|string|min:10|max:5000',
            'recaptcha_token'  => 'required|string',
        ]);

        // Verify reCAPTCHA token with Google
        if (!$this->verifyRecaptcha($request->recaptcha_token)) {
            return response()->json([
                'message' => 'CAPTCHA verification failed. Please try again.',
            ], 422);
        }

        $submission = ContactSubmission::create([
            'name'       => $request->name,
            'email'      => $request->email,
            'subject'    => $request->subject,
            'message'    => $request->message,
            'ip_address' => $request->ip(),
        ]);

        return response()->json([
            'message' => 'Thank you for contacting us. We will get back to you soon.',
            'data'    => [
                'id'         => $submission->id,
                'name'       => $submission->name,
                'email'      => $submission->email,
                'subject'    => $submission->subject,
                'created_at' => $submission->created_at,
            ],
        ], 201);
    }

    /**
     * GET /api/admin/contact-submissions
     * Admin — list all submissions with filters.
     */
    public function index(Request $request): JsonResponse
    {
        $query = ContactSubmission::query()->latest();

        if ($request->filled('status')) {
            $query->where('status', $request->status);
        }

        if ($request->filled('search')) {
            $search = $request->search;
            $query->where(function ($q) use ($search) {
                $q->where('name', 'like', "%{$search}%")
                  ->orWhere('email', 'like', "%{$search}%")
                  ->orWhere('subject', 'like', "%{$search}%");
            });
        }

        return response()->json($query->paginate(20));
    }

    /**
     * GET /api/admin/contact-submissions/{id}
     * Admin — view a single submission (also marks it as read).
     */
    public function show(ContactSubmission $contactSubmission): JsonResponse
    {
        if ($contactSubmission->status === 'new') {
            $contactSubmission->update(['status' => 'read']);
        }

        return response()->json(['data' => $contactSubmission]);
    }

    /**
     * PATCH /api/admin/contact-submissions/{id}/status
     * Admin — update status (new / read / replied).
     */
    public function updateStatus(Request $request, ContactSubmission $contactSubmission): JsonResponse
    {
        $request->validate([
            'status' => 'required|in:new,read,replied',
        ]);

        $contactSubmission->update(['status' => $request->status]);

        return response()->json(['message' => 'Status updated.', 'data' => $contactSubmission]);
    }

    /**
     * DELETE /api/admin/contact-submissions/{id}
     */
    public function destroy(ContactSubmission $contactSubmission): JsonResponse
    {
        $contactSubmission->delete();

        return response()->json(['message' => 'Submission deleted.']);
    }

    /**
     * Verify Google reCAPTCHA v3 token.
     */
    private function verifyRecaptcha(string $token): bool
    {
        $secret = config('services.recaptcha.secret');

        // Skip verification in local/testing environments if secret not set
        if (empty($secret)) {
            return true;
        }

        $response = Http::asForm()->post('https://www.google.com/recaptcha/api/siteverify', [
            'secret'   => $secret,
            'response' => $token,
        ]);

        if (!$response->successful()) {
            return false;
        }

        $result = $response->json();

        // For reCAPTCHA v3: also check score threshold (0.5 is Google's recommended minimum)
        if (isset($result['score'])) {
            return ($result['success'] === true) && ($result['score'] >= 0.5);
        }

        // For reCAPTCHA v2 (checkbox)
        return $result['success'] === true;
    }
}
