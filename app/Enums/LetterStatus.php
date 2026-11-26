<?php

namespace App\Enums;

use App\Enums\Concerns\HasLabel;

/**
 * draft: پیش‌نویس (شماره ندارد، قابل ویرایش). issued: صادر یا ثبت شد (شماره‌ی پشت‌سرهم؛ متن دیگر عوض نمی‌شود).
 * cancelled: باطل با دلیل (شماره‌اش دوباره داده نمی‌شود). مکتوب هرگز حذف نمی‌شود.
 */
enum LetterStatus: string
{
    use HasLabel;

    case Draft = 'draft';
    case Issued = 'issued';
    case Cancelled = 'cancelled';

    public function badgeClass(): string
    {
        return match ($this) {
            self::Draft => 'text-bg-warning',
            self::Issued => 'text-bg-success',
            self::Cancelled => 'text-bg-secondary',
        };
    }
}
