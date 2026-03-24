<?php

namespace App\Enums;

enum QuestionStatus: string
{
    case Draft = 'draft';
    case Review = 'review';
    case Approved = 'approved';
    case Archived = 'archived';
    case Rejected = 'rejected';
}