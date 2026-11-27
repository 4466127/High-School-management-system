<?php

namespace App\Enums;

use App\Enums\Concerns\HasLabel;

/** وضعیت امانت (مرحله‌ی ۱۷). */
enum LoanStatus: string
{
    use HasLabel;

    case Active = 'active';
    case Returned = 'returned';
    case Lost = 'lost';

    public function badgeClass(): string
    {
        return match ($this) {
            self::Active => 'text-bg-primary',
            self::Returned => 'text-bg-success',
            self::Lost => 'text-bg-danger',
        };
    }
}
