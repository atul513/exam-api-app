<?php

// ─────────────────────────────────────────────────────────────
// FILE: app/Observers/TopicObserver.php
// ─────────────────────────────────────────────────────────────

namespace App\Observers;

use App\Models\Topic;
use App\Services\QuestionCacheService;

class TopicObserver
{
    public function created(Topic $topic): void
    {
        QuestionCacheService::flushTopics($topic->subject_id);
    }

    public function updated(Topic $topic): void
    {
        QuestionCacheService::flushTopics($topic->subject_id);

        // If moved to different subject, flush old one too
        if ($topic->wasChanged('subject_id')) {
            QuestionCacheService::flushTopics($topic->getOriginal('subject_id'));
        }
    }

    public function deleted(Topic $topic): void
    {
        QuestionCacheService::flushTopics($topic->subject_id);
    }
}
