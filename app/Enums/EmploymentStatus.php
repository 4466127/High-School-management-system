<?php

namespace App\Enums;

use App\Enums\Concerns\HasLabel;

enum EmploymentStatus: string
{
    use HasLabel;

    case Active = 'active';
    case OnLeave = 'on_leave';
    case Left = 'left';

    public function badgeClass(): string
    {
        return match ($this) {
            self::Active => 'text-bg-success',
            self::OnLeave => 'text-bg-warning',
            self::Left => 'text-bg-secondary',
        };
    }
}
