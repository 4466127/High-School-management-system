<?php

namespace App\Enums;

use App\Enums\Concerns\HasLabel;

/** نوع امانت (مرحله‌ی ۱۷): امانت عادی یا توزیع کتاب درسی برای تمام سال. */
enum LoanKind: string
{
    use HasLabel;

    case Loan = 'loan';
    case Textbook = 'textbook';
}
