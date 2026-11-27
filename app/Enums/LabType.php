<?php

namespace App\Enums;

use App\Enums\Concerns\HasLabel;

/** نوع لابراتوار (مرحله‌ی ۱۸). */
enum LabType: string
{
    use HasLabel;

    case Chemistry = 'chemistry';
    case Physics = 'physics';
    case Biology = 'biology';
    case Computer = 'computer';
    case General = 'general';
}
