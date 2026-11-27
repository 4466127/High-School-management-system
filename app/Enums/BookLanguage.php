<?php

namespace App\Enums;

use App\Enums\Concerns\HasLabel;

/** زبان کتاب (مرحله‌ی ۱۷). */
enum BookLanguage: string
{
    use HasLabel;

    case Dari = 'dari';
    case Pashto = 'pashto';
    case English = 'english';
    case Arabic = 'arabic';
    case Other = 'other';
}
