<?php

// ─────────────────────────────────────────────────────────────
// FILE: app/Observers/SubjectObserver.php
// ─────────────────────────────────────────────────────────────

namespace App\Observers;

use App\Models\Subject;
use App\Services\QuestionCacheService;

class SubjectObserver
{
    public function created(Subject $subject): void
    {
        QuestionCacheService::flushSubjects();
    }

    public function updated(Subject $subject): void
    {
        QuestionCacheService::flushSubjects();
    }

    public function deleted(Subject $subject): void
    {
        QuestionCacheService::flushSubjects();
        QuestionCacheService::flushTopics($subject->id);
    }
}

