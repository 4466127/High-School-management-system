<?php

namespace App\Enums;

use App\Enums\Concerns\HasLabel;

/** حالت فزیکی جلد (مرحله‌ی ۱۷). */
enum CopyCondition: string
{
    use HasLabel;

    case New = 'new';
    case Good = 'good';
    case Fair = 'fair';
    case Poor = 'poor';
}
