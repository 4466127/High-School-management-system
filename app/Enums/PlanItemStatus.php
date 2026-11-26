<?php

namespace App\Enums;

use App\Enums\Concerns\HasLabel;

/** پیشرفت یک موضوع پلان تدریس (مرحله‌ی ۱۶). */
enum PlanItemStatus: string
{
    use HasLabel;

    case Planned = 'planned';
    case Done = 'done';
    case Partial = 'partial';
    case Skipped = 'skipped';

    public function badgeClass(): string
    {
        return match ($this) {
            self::Planned => 'text-bg-light border',
            self::Done => 'text-bg-success',
            self::Partial => 'text-bg-warning',
            self::Skipped => 'text-bg-secondary',
        };
    }
}
