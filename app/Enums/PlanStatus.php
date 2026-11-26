<?php

namespace App\Enums;

use App\Enums\Concerns\HasLabel;

/** وضعیت پلان تدریس (مرحله‌ی ۱۶): پیش‌نویس ← فرستاده ← منظور، یا برگشت برای اصلاح. */
enum PlanStatus: string
{
    use HasLabel;

    case Draft = 'draft';
    case Submitted = 'submitted';
    case Approved = 'approved';
    case Returned = 'returned';

    public function badgeClass(): string
    {
        return match ($this) {
            self::Draft => 'text-bg-secondary',
            self::Submitted => 'text-bg-info',
            self::Approved => 'text-bg-success',
            self::Returned => 'text-bg-warning',
        };
    }

    /** موضوع‌ها افزوده/حذف/ویرایش شوند؟ (پیشرفت همیشه ثبت می‌شود) */
    public function editable(): bool
    {
        return in_array($this, [self::Draft, self::Returned], true);
    }
}
