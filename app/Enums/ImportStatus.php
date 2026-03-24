<?php

namespace App\Enums;

enum ImportStatus: string
{
    case Pending = 'pending';
    case Validating = 'validating';
    case Processing = 'processing';
    case Completed = 'completed';
    case Failed = 'failed';
    case Partial = 'partial';
}