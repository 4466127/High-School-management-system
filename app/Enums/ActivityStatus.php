<?php

namespace App\Enums;

use App\Enums\Concerns\HasLabel;

/** وضعیت فعالیت عملی (مرحله‌ی ۱۸). */
enum ActivityStatus: string
{
    use HasLabel;

    case Planned = 'planned';
    case Done = 'done';
    case Cancelled = 'cancelled';

    public function badgeClass(): string
    {
        return match ($this) {
            self::Planned => 'text-bg-info',
            self::Done => 'text-bg-success',
            self::Cancelled => 'text-bg-secondary',
        };
    }
}
