<?php

namespace App\Http\Controllers\Attendance\Concerns;

use App\Models\AcademicYear;
use App\Services\AttendanceService;
use App\Support\Digits;
use App\Support\JalaliDate;
use Carbon\CarbonImmutable;
use Illuminate\Http\Request;

/**
 * خواندن تاریخ و ماه از آدرس صفحه‌های حاضری.
 * date: «2026-11-16» (پیوندهای داخلی) یا «۱۴۰۵/۰۸/۲۵» (خانه‌ی تاریخ)؛ نادرست یا خالی = امروز.
 * month: «1405-08» (ماه شمسی)؛ نادرست یا خالی = ماه جاری.
 */
trait ResolvesDates
{
    protected function dateFrom(Request $request): CarbonImmutable
    {
        $input = Digits::toLatin(trim((string) $request->query('date', '')));

        if (preg_match('/^(\d{4})-(\d{2})-(\d{2})$/', $input, $m) && (int) $m[1] > 1900 && checkdate((int) $m[2], (int) $m[3], (int) $m[1])) {
            return CarbonImmutable::createFromFormat('!Y-m-d', $input, JalaliDate::TIMEZONE);
        }

        return JalaliDate::parse($input) ?? AttendanceService::today();
    }

    /** @return array{year:int, month:int, from:CarbonImmutable, to:CarbonImmutable, key:string} */
    protected function monthFrom(Request $request): array
    {
        $input = Digits::toLatin((string) $request->query('month', ''));
        if (preg_match('/^(\d{4})-(\d{1,2})$/', $input, $m) && JalaliDate::isValid((int) $m[1], (int) $m[2], 1)) {
            [$y, $mo] = [(int) $m[1], (int) $m[2]];
        } else {
            [$y, $mo] = JalaliDate::fromGregorian(AttendanceService::today());
        }
        [$from, $to] = JalaliDate::monthRange($y, $mo);

        return ['year' => $y, 'month' => $mo, 'from' => $from, 'to' => $to, 'key' => sprintf('%04d-%02d', $y, $mo)];
    }

    /** @return array<string, string> «1405-08» => «عقرب ۱۴۰۵» — ماه‌های سال تحصیلی (یا ۱۲ ماه اخیر) */
    protected function monthOptions(?AcademicYear $year): array
    {
        $today = AttendanceService::today();
        $months = $year
            ? JalaliDate::monthsBetween($year->start_date, $year->end_date->min($today->max($year->start_date)))
            : JalaliDate::monthsBetween($today->subMonths(11), $today);

        $options = [];
        foreach (array_reverse($months) as [$y, $m]) {
            $options[sprintf('%04d-%02d', $y, $m)] = JalaliDate::monthName($m).' '.Digits::toPersian((string) $y);
        }

        return $options;
    }

    protected function inYear(?AcademicYear $year, CarbonImmutable $date): bool
    {
        return $year !== null
            && $date->format('Y-m-d') >= $year->start_date->format('Y-m-d')
            && $date->format('Y-m-d') <= $year->end_date->format('Y-m-d');
    }
}
