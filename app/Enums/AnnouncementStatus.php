<?php

namespace App\Enums;

use App\Enums\Concerns\HasLabel;

enum AnnouncementStatus: string
{
    use HasLabel;

    case Draft = 'draft';
    case Published = 'published';
    case Archived = 'archived';

    public function badgeClass(): string
    {
        return match ($this) {
            self::Draft => 'text-bg-secondary',
            self::Published => 'text-bg-success',
            self::Archived => 'text-bg-light border',
        };
    }
}
