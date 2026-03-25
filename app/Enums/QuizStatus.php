<?php
// ─── app/Enums/QuizStatus.php ───────────────────────────────

namespace App\Enums;

enum QuizStatus: string
{
    case Draft = 'draft';
    case Published = 'published';
    case Archived = 'archived';
}
