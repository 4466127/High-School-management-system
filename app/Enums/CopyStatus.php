<?php

namespace App\Enums;

use App\Enums\Concerns\HasLabel;

/** وضعیت هر جلد کتاب (مرحله‌ی ۱۷). جلد حذف نمی‌شود؛ خراب، گم یا خارج از استفاده می‌شود. */
enum CopyStatus: string
{
    use HasLabel;

    case Available = 'available';
    case OnLoan = 'on_loan';
    case Damaged = 'damaged';
    case Lost = 'lost';
    case Withdrawn = 'withdrawn';

    public function badgeClass(): string
    {
        return match ($this) {
            self::Available => 'text-bg-success',
            self::OnLoan => 'text-bg-primary',
            self::Damaged => 'text-bg-warning',
            self::Lost => 'text-bg-danger',
            self::Withdrawn => 'text-bg-secondary',
        };
    }
}
