<?php

// ─── app/Traits/HasShareLinks.php ───────────────────────────
// Add to Quiz, PracticeSet, CodingTest models

namespace App\Traits;

use App\Models\{ShareLink, Invitation};
use Illuminate\Database\Eloquent\Relations\{MorphMany, MorphOne};

trait HasShareLinks
{
    public function shareLinks(): MorphMany
    {
        return $this->morphMany(ShareLink::class, 'shareable');
    }

    public function activeShareLink(): MorphOne
    {
        return $this->morphOne(ShareLink::class, 'shareable')
            ->where('is_active', true)
            ->latest();
    }

    public function invitations(): MorphMany
    {
        return $this->morphMany(Invitation::class, 'invitable');
    }

    /**
     * Get or create a public share link.
     */
    public function getOrCreateShareLink(int $userId, array $options = []): ShareLink
    {
        $existing = $this->shareLinks()->where('is_active', true)->first();
        if ($existing) return $existing;

        return $this->shareLinks()->create([
            'title'                => $options['title'] ?? $this->title,
            'message'              => $options['message'] ?? null,
            'is_active'            => true,
            'require_registration' => $options['require_registration'] ?? true,
            'expires_at'           => $options['expires_at'] ?? null,
            'max_registrations'    => $options['max_registrations'] ?? null,
            'created_by'           => $userId,
        ]);
    }
}
