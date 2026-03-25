<?php

// ─── app/Enums/AccessType.php ───────────────────────────────

namespace App\Enums;

enum AccessType: string
{
    case Free = 'free';
    case Paid = 'paid';
}
