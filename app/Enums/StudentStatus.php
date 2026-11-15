<?php

namespace App\Enums;

use App\Enums\Concerns\HasLabel;

/**
 * وضعیت پرونده‌ی دانش‌آموز. در مرحله‌ی ۳ فقط active/inactive از صفحه تغییر می‌کند؛
 * transferred، dropped و graduated را مرحله‌ی ۴ همراه شمولیت سالانه (enrollments) می‌گذارد.
 */
enum StudentStatus: string
{
    use HasLabel;

    case Active = 'active';
    case Inactive = 'inactive';
    case Transferred = 'transferred';
    case Dropped = 'dropped';
    case Graduated = 'graduated';

    public function badgeClass(): string
    {
        return match ($this) {
            self::Active => 'text-bg-success',
            self::Graduated => 'text-bg-primary',
            default => 'text-bg-secondary',
        };
    }
}
