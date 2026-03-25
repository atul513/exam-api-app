<?php

// ─────────────────────────────────────────────────────────────
// FILE: app/Observers/QuestionObserver.php
// ─────────────────────────────────────────────────────────────

namespace App\Observers;

use App\Models\Question;
use App\Services\QuestionCacheService;

class QuestionObserver
{
    public function created(Question $question): void
    {
        QuestionCacheService::flushQuestion($question->id);
        QuestionCacheService::flushStats();
    }

    public function updated(Question $question): void
    {
        QuestionCacheService::flushQuestion($question->id);

        // If subject or topic changed, flush those too
        if ($question->wasChanged('subject_id')) {
            QuestionCacheService::flushTopics($question->getOriginal('subject_id'));
            QuestionCacheService::flushTopics($question->subject_id);
        }
        if ($question->wasChanged('topic_id')) {
            QuestionCacheService::flushStats();
        }
    }

    public function deleted(Question $question): void
    {
        QuestionCacheService::flushQuestion($question->id);
        QuestionCacheService::flushStats();
    }

    public function restored(Question $question): void
    {
        QuestionCacheService::flushQuestion($question->id);
        QuestionCacheService::flushStats();
    }
}

