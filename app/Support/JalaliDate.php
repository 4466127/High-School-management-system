<?php

namespace App\Support;

use Carbon\CarbonImmutable;
use DateTimeImmutable;
use DateTimeInterface;
use DateTimeZone;
use IntlCalendar;
use InvalidArgumentException;

/**
 * تاریخ هجری شمسی با افزونه‌ی intl خود PHP (تقویم persian). بدون بسته‌ی بیرونی.
 * پایگاه داده همیشه میلادی است؛ این کلاس فقط برای نمایش و خواندن ورودی کاربر است.
 * نام ماه‌ها (حمل … حوت) و روزها از lang/fa/dates.php می‌آید تا وابسته به نسخه‌ی ICU نباشد.
 */
final class JalaliDate
{
    public const TIMEZONE = 'Asia/Kabul';

    private const LOCALE = 'en_US@calendar=persian';

    /** @return array{0:int,1:int,2:int} [سال، ماه، روز] */
    public static function fromGregorian(DateTimeInterface|string $date): array
    {
        $cal = self::calendar();
        $cal->setTime(self::noonTimestamp($date) * 1000.0);

        return [
            $cal->get(IntlCalendar::FIELD_EXTENDED_YEAR),
            $cal->get(IntlCalendar::FIELD_MONTH) + 1,
            $cal->get(IntlCalendar::FIELD_DAY_OF_MONTH),
        ];
    }

    public static function toGregorian(int $year, int $month, int $day): CarbonImmutable
    {
        if (! self::isValid($year, $month, $day)) {
            throw new InvalidArgumentException("Invalid Jalali date: {$year}/{$month}/{$day}");
        }

        $cal = self::calendar();
        $cal->clear();
        $cal->setDateTime($year, $month - 1, $day, 12, 0, 0);

        return CarbonImmutable::createFromTimestamp(intdiv((int) $cal->getTime(), 1000), self::TIMEZONE)->startOfDay();
    }

    public static function isValid(int $year, int $month, int $day): bool
    {
        return $year >= 1300 && $year <= 1500
            && $month >= 1 && $month <= 12
            && $day >= 1 && $day <= self::daysInMonth($year, $month);
    }

    public static function daysInMonth(int $year, int $month): int
    {
        if ($month <= 6) {
            return 31;
        }
        if ($month <= 11) {
            return 30;
        }

        return self::isLeapYear($year) ? 30 : 29;
    }

    public static function isLeapYear(int $year): bool
    {
        $cal = self::calendar();
        $cal->clear();
        $cal->setDate($year, 11, 1);

        return $cal->getActualMaximum(IntlCalendar::FIELD_DAY_OF_MONTH) === 30;
    }

    /**
     * خواندن تاریخ نوشته‌شده توسط کاربر: «۱۴۰۵/۰۸/۲۴»، «1405-8-24»، «05/8/24» (سال دو رقمی = ۱۴xx).
     * همان قاعده‌ی انتخاب‌گر تاریخ در public/assets/js/app.js.
     */
    public static function parse(?string $input): ?CarbonImmutable
    {
        if ($input === null) {
            return null;
        }

        if (! preg_match('~^\s*(\d{2,4})\s*[/\-.]\s*(\d{1,2})\s*[/\-.]\s*(\d{1,2})\s*$~', Digits::toLatin($input), $m)) {
            return null;
        }

        $year = (int) $m[1];
        if ($year < 100) {
            $year += 1400;
        }

        return self::isValid($year, (int) $m[2], (int) $m[3])
            ? self::toGregorian($year, (int) $m[2], (int) $m[3])
            : null;
    }

    /**
     * نمایش تاریخ میلادی به شمسی با ارقام فارسی.
     * short: ۱۴۰۵/۰۸/۲۴ — long: ۲۴ عقرب ۱۴۰۵ — full: یکشنبه ۲۴ عقرب ۱۴۰۵
     */
    public static function format(DateTimeInterface|string|null $date, string $style = 'short'): string
    {
        if ($date === null || $date === '') {
            return '';
        }

        [$y, $m, $d] = self::fromGregorian($date);

        $text = match ($style) {
            'short' => sprintf('%04d/%02d/%02d', $y, $m, $d),
            'long' => $d.' '.self::monthName($m).' '.$y,
            'full' => self::weekdayName($date).' '.$d.' '.self::monthName($m).' '.$y,
            default => throw new InvalidArgumentException("Unknown style: {$style}"),
        };

        return Digits::toPersian($text);
    }

    public static function monthName(int $month): string
    {
        return __('dates.months')[$month - 1] ?? (string) $month;
    }

    /** @return array{0:CarbonImmutable,1:CarbonImmutable} اولین و آخرین روز یک ماه شمسی (میلادی) */
    public static function monthRange(int $year, int $month): array
    {
        return [self::toGregorian($year, $month, 1), self::toGregorian($year, $month, self::daysInMonth($year, $month))];
    }

    /**
     * ماه‌های شمسی میان دو تاریخ (برای انتخاب ماه در گزارش ماهوار).
     *
     * @return list<array{0:int,1:int}> [[سال، ماه], …]
     */
    public static function monthsBetween(DateTimeInterface|string $from, DateTimeInterface|string $to): array
    {
        [$y, $m] = self::fromGregorian($from);
        [$toY, $toM] = self::fromGregorian($to);
        $months = [];
        while (($y < $toY || ($y === $toY && $m <= $toM)) && count($months) < 36) {
            $months[] = [$y, $m];
            [$y, $m] = $m === 12 ? [$y + 1, 1] : [$y, $m + 1];
        }

        return $months;
    }

    private static function weekdayName(DateTimeInterface|string $date): string
    {
        // ISO: ۱ = دوشنبه … ۷ = یکشنبه
        $iso = (int) (new DateTimeImmutable('@'.self::noonTimestamp($date)))->setTimezone(new DateTimeZone(self::TIMEZONE))->format('N');

        return __('dates.weekdays')[$iso] ?? '';
    }

    /** ساعت ۱۲ ظهر کابل در همان روز (تا تغییر ساعت یا منطقه‌ی زمانی روز را عوض نکند). */
    private static function noonTimestamp(DateTimeInterface|string $date): int
    {
        $tz = new DateTimeZone(self::TIMEZONE);
        $local = $date instanceof DateTimeInterface
            ? DateTimeImmutable::createFromInterface($date)->setTimezone($tz)
            : new DateTimeImmutable($date, $tz);

        return (new DateTimeImmutable($local->format('Y-m-d').' 12:00:00', $tz))->getTimestamp();
    }

    private static function calendar(): IntlCalendar
    {
        return IntlCalendar::createInstance(self::TIMEZONE, self::LOCALE);
    }
}
