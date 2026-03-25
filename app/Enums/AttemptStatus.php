<?php

// ─── app/Enums/AttemptStatus.php ────────────────────────────

namespace App\Enums;

enum AttemptStatus: string
{
    case InProgress = 'in_progress';
    case Submitted = 'submitted';
    case AutoSubmitted = 'auto_submitted';
    case Abandoned = 'abandoned';
    case Grading = 'grading';
    case Completed = 'completed';
}
