<?php

namespace Tests\Unit;

use App\Support\JalaliDate;
use Carbon\CarbonImmutable;
use InvalidArgumentException;
use PHPUnit\Framework\Attributes\DataProvider;
use Tests\TestCase;

class JalaliDateTest extends TestCase
{
    public static function knownDates(): array
    {
        return [
            'nowruz 1405' => ['2026-03-21', [1405, 1, 1]],
            'design day' => ['2026-11-15', [1405, 8, 24]],
            'last day of leap 1403' => ['2025-03-20', [1403, 12, 30]],
            'nowruz 1404' => ['2025-03-21', [1404, 1, 1]],
            'end of 1404 (not leap)' => ['2026-03-20', [1404, 12, 29]],
            'first of mizan' => ['2026-09-23', [1405, 7, 1]],
        ];
    }

    #[DataProvider('knownDates')]
    public function test_converts_known_dates_both_ways(string $gregorian, array $jalali): void
    {
        $this->assertSame($jalali, JalaliDate::fromGregorian($gregorian));
        $this->assertSame($gregorian, JalaliDate::toGregorian(...$jalali)->toDateString());
    }

    public function test_leap_years_and_month_lengths(): void
    {
        $this->assertTrue(JalaliDate::isLeapYear(1403));
        $this->assertFalse(JalaliDate::isLeapYear(1404));
        $this->assertSame(31, JalaliDate::daysInMonth(1405, 6));
        $this->assertSame(30, JalaliDate::daysInMonth(1405, 7));
        $this->assertSame(30, JalaliDate::daysInMonth(1403, 12));
        $this->assertSame(29, JalaliDate::daysInMonth(1404, 12));
        $this->assertFalse(JalaliDate::isValid(1404, 12, 30));
        $this->assertFalse(JalaliDate::isValid(1405, 13, 1));
        $this->assertFalse(JalaliDate::isValid(1405, 7, 31));
    }

    public function test_invalid_date_throws(): void
    {
        $this->expectException(InvalidArgumentException::class);
        JalaliDate::toGregorian(1404, 12, 30);
    }

    /**
     * سرور (ICU) و انتخاب‌گر تاریخ مرورگر (الگوریتم ۳۳ساله در public/assets/js/app.js) باید روز به روز یکی باشند.
     * از ۱۳۷۰ تا ۱۴۵۰ (حدود ۲۹٬۰۰۰ روز) مقایسه می‌شود.
     */
    public function test_matches_browser_date_picker_algorithm_every_day(): void
    {
        $day = CarbonImmutable::parse('1991-03-21', JalaliDate::TIMEZONE); // ۱ حمل ۱۳۷۰
        $end = CarbonImmutable::parse('2071-03-21', JalaliDate::TIMEZONE);

        while ($day < $end) {
            $expected = self::jsG2j((int) $day->format('Y'), (int) $day->format('n'), (int) $day->format('j'));
            $this->assertSame($expected, JalaliDate::fromGregorian($day), 'Mismatch on '.$day->toDateString());
            $day = $day->addDay();
        }
    }

    public function test_round_trip_for_every_day_of_several_years(): void
    {
        for ($y = 1399; $y <= 1410; $y++) {
            for ($m = 1; $m <= 12; $m++) {
                for ($d = 1; $d <= JalaliDate::daysInMonth($y, $m); $d++) {
                    $this->assertSame([$y, $m, $d], JalaliDate::fromGregorian(JalaliDate::toGregorian($y, $m, $d)));
                }
            }
        }
    }

    public function test_parses_user_input(): void
    {
        $this->assertSame('2026-11-15', JalaliDate::parse('۱۴۰۵/۰۸/۲۴')?->toDateString());
        $this->assertSame('2026-11-15', JalaliDate::parse('1405-8-24')?->toDateString());
        $this->assertSame('2026-11-15', JalaliDate::parse(' 05/8/24 ')?->toDateString());
        $this->assertSame('2026-11-15', JalaliDate::parse('١٤٠٥.٠٨.٢٤')?->toDateString());
        $this->assertNull(JalaliDate::parse('1405/13/01'));
        $this->assertNull(JalaliDate::parse('1404/12/30'));
        $this->assertNull(JalaliDate::parse('abc'));
        $this->assertNull(JalaliDate::parse(''));
        $this->assertNull(JalaliDate::parse(null));
    }

    public function test_formats_with_afghan_month_names_and_persian_digits(): void
    {
        $this->assertSame('۱۴۰۵/۰۸/۲۴', JalaliDate::format('2026-11-15'));
        $this->assertSame('۲۴ عقرب ۱۴۰۵', JalaliDate::format('2026-11-15', 'long'));
        $this->assertSame('یکشنبه ۲۴ عقرب ۱۴۰۵', JalaliDate::format('2026-11-15', 'full'));
        $this->assertSame('۱ حمل ۱۴۰۵', JalaliDate::format('2026-03-21', 'long'));
        $this->assertSame('', JalaliDate::format(null));
    }

    public function test_uses_kabul_time_for_datetimes(): void
    {
        // ۲۲:۰۰ UTC روز ۱۵ نوامبر = ۰۲:۳۰ کابل روز ۱۶ نوامبر
        $this->assertSame([1405, 8, 25], JalaliDate::fromGregorian(CarbonImmutable::parse('2026-11-15 22:00:00', 'UTC')));
    }

    /** همان تابع g2j در public/assets/js/app.js */
    private static function jsG2j(int $gy, int $gm, int $gd): array
    {
        $div = fn (int $a, int $b) => intdiv($a, $b);
        $before = [0, 31, 59, 90, 120, 151, 181, 212, 243, 273, 304, 334];
        $gy2 = $gm > 2 ? $gy + 1 : $gy;
        $days = 355666 + 365 * $gy + $div($gy2 + 3, 4) - $div($gy2 + 99, 100) + $div($gy2 + 399, 400) + $gd + $before[$gm - 1];
        $jy = -1595 + 33 * $div($days, 12053);
        $days %= 12053;
        $jy += 4 * $div($days, 1461);
        $days %= 1461;
        if ($days > 365) {
            $jy += $div($days - 1, 365);
            $days = ($days - 1) % 365;
        }

        return $days < 186
            ? [$jy, 1 + $div($days, 31), 1 + ($days % 31)]
            : [$jy, 7 + $div($days - 186, 30), 1 + (($days - 186) % 30)];
    }
}
