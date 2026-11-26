<?php

namespace App\Enums;

use App\Enums\Concerns\HasLabel;

/**
 * مخاطب اعلان. کاربران سیستم کارکنان مکتب‌اند؛ اعلان «دانش‌آموزان» و «والدین» را همه‌ی کارکنان می‌بینند
 * و چاپ می‌کنند (تخته‌ی اعلانات). «معلمان» فقط برای معلمان و مدیران اعلان دیده می‌شود.
 */
enum AnnouncementAudience: string
{
    use HasLabel;

    case All = 'all';
    case Staff = 'staff';
    case Teachers = 'teachers';
    case Students = 'students';
    case Parents = 'parents';
}
