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

    public static function isWeeklyHoliday(DateTimeInterface $date): bool
    {
        return in_array(strtolower($date->format('l')), self::weeklyHolidays(), true);
    }
}
