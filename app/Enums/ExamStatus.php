<?php

namespace App\Enums;

use App\Enums\Concerns\HasLabel;

/**
 * draft: تعریف شده، هنوز نمره ثبت نمی‌شود. open: معلمان نمره ثبت می‌کنند.
 * locked: قفل؛ نمره فقط با مجوز exams.manage تغییر می‌کند.
 */
enum ExamStatus: string
{
    use HasLabel;

    case Draft = 'draft';
    case Open = 'open';
    case Locked = 'locked';

    public function badgeClass(): string
    {
        return match ($this) {
            self::Draft => 'text-bg-secondary',
            self::Open => 'text-bg-success',
            self::Locked => 'text-bg-dark',
        };
    }
}
