<?php

namespace App\Enums;

use App\Enums\Concerns\HasLabel;

/** وضعیت اشتراک‌کننده‌ی کورس. */
enum ParticipantStatus: string
{
    use HasLabel;

    case Active = 'active';
    case Left = 'left';
    case Completed = 'completed';

    public function badgeClass(): string
    {
        return match ($this) {
            self::Active => 'text-bg-success',
            self::Left => 'text-bg-secondary',
            self::Completed => 'text-bg-primary',
        };
    }
}
