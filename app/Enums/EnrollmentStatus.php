<?php

namespace App\Enums;

use App\Enums\Concerns\HasLabel;

/**
 * وضعیت شمولیت سالانه.
 * active: در همین سال درس می‌خواند. promoted/repeated/graduated: نتیجه‌ی پایان سال (سطر بسته شده).
 * transferred/dropped: در جریان سال رفته است.
 */
enum EnrollmentStatus: string
{
    use HasLabel;

    case Active = 'active';
    case Promoted = 'promoted';
    case Repeated = 'repeated';
    case Graduated = 'graduated';
    case Transferred = 'transferred';
    case Dropped = 'dropped';

    public function badgeClass(): string
    {
        return match ($this) {
            self::Active => 'text-bg-success',
            self::Promoted, self::Graduated => 'text-bg-primary',
            self::Repeated => 'text-bg-warning',
            self::Transferred, self::Dropped => 'text-bg-secondary',
        };
    }
}
