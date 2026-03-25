<?php

// ─── app/Enums/QuizType.php ─────────────────────────────────

namespace App\Enums;

enum QuizType: string
{
    case Quiz = 'quiz';
    case Exam = 'exam';
}