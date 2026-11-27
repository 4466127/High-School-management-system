<?php

namespace App\Enums;

use App\Enums\Concerns\HasLabel;

/** از کجا رسیده است (مرحله‌ی ۱۷). */
enum CopySource: string
{
    use HasLabel;

    case Purchase = 'purchase';
    case Donation = 'donation';
    case Ministry = 'ministry';
    case Other = 'other';
}
