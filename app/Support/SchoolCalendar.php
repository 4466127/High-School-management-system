<?php

namespace App\Support;

use App\Models\Setting;
use DateTimeInterface;

/** تقویم مکتب: رخصتی هفته از تنظیمات (weekly_holidays، مثلاً «friday» یا «thursday,friday»). */
final class SchoolCalendar
{
    /** @return list<string> */
    public static function weeklyHolidays(): array
    {
        return array_values(array_filter(explode(',', (string) Setting::get('weekly_holidays'))));
    }

    /** روزهای درسی هفته به ترتیب (شنبه تا جمعه، بدون رخصتی‌ها) — تقسیم اوقات (مرحله‌ی ۱۵) */
    public static function schoolDays(): array
    {
        $week = ['saturday', 'sunday', 'monday', 'tuesday', 'wednesday', 'thursday', 'friday'];

        return array_values(array_diff($week, self::weeklyHolidays()));
    }

    public static function isWeeklyHoliday(DateTimeInterface $date): bool
    {
        return in_array(strtolower($date->format('l')), self::weeklyHolidays(), true);
    }
}
