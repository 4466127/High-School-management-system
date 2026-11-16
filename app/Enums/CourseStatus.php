<?php

namespace App\Enums;

use App\Enums\Concerns\HasLabel;

/** وضعیت کورس. */
enum CourseStatus: string
{
    use HasLabel;

    case Planned = 'planned';
    case Active = 'active';
    case Finished = 'finished';
    case Cancelled = 'cancelled';

    public function badgeClass(): string
    {
        return match ($this) {
            self::Planned => 'text-bg-light border',
            self::Active => 'text-bg-success',
            self::Finished => 'text-bg-secondary',
            self::Cancelled => 'text-bg-dark',
        };
    }
}
