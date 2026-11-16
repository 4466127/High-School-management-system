<?php

namespace App\Enums;

use App\Enums\Concerns\HasLabel;

/**
 * وضعیت حاضری (دانش‌آموز و کارمند)، مطابق کتاب حاضری مکاتب: حاضر، تأخیر، غیرحاضر، رخصت، مریض.
 * «تأخیر» حاضر حساب می‌شود. «غیرحاضر» غیابت بی‌اجازه است و در فهرست غیابت زیاد و حد «محروم» شمرده می‌شود؛
 * «رخصت» و «مریض» غیابت موجه‌اند و جدا نشان داده می‌شوند.
 */
enum AttendanceStatus: string
{
    use HasLabel;

    case Present = 'present';
    case Late = 'late';
    case Absent = 'absent';
    case Excused = 'excused';
    case Sick = 'sick';

    /** یک حرف برای جدول ماهوار: ح، ت، غ، ر، م */
    public function code(): string
    {
        return __('enums.attendance_status_code.'.$this->value);
    }

    public function isPresent(): bool
    {
        return $this === self::Present || $this === self::Late;
    }

    public function badgeClass(): string
    {
        return match ($this) {
            self::Present => 'text-bg-success',
            self::Late => 'text-bg-info',
            self::Absent => 'text-bg-danger',
            self::Excused => 'text-bg-warning',
            self::Sick => 'text-bg-secondary',
        };
    }

    /** رنگ دکمه‌ی انتخاب در ورق حاضری (btn-outline-*) */
    public function buttonClass(): string
    {
        return match ($this) {
            self::Present => 'success',
            self::Late => 'info',
            self::Absent => 'danger',
            self::Excused => 'warning',
            self::Sick => 'secondary',
        };
    }
}
