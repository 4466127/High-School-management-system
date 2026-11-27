<?php

namespace App\Support;

use DateTimeInterface;

/**
 * نمایش وقت به شکل ۱۲ ساعته (مثل افغانستان): «13:40» ← «۱:۴۰»؛ با نشان «ق.ظ / ب.ظ» برای وقت روز (رویدادها، پشتیبان).
 * در پایگاه داده و فورم‌ها وقت همیشه ۲۴ ساعته (H:i) می‌ماند؛ فقط نمایش عوض می‌شود.
 */
final class Clock
{
    /** «13:40» یا «13:40:00» یا DateTime ← «۱:۴۰» (بی نشان) */
    public static function short(DateTimeInterface|string|null $time): string
    {
        [$h, $m] = self::parts($time);
        if ($h === null) {
            return '';
        }
        $h12 = $h % 12 === 0 ? 12 : $h % 12;

        return Digits::toPersian($h12.':'.str_pad((string) $m, 2, '0', STR_PAD_LEFT));
    }

    /** «۱:۴۰ ب.ظ» */
    public static function withPeriod(DateTimeInterface|string|null $time): string
    {
        [$h] = self::parts($time);
        if ($h === null) {
            return '';
        }

        return self::short($time).' '.__($h < 12 ? 'app.am' : 'app.pm');
    }

    /** «۸:۰۰ – ۸:۴۰» */
    public static function range(DateTimeInterface|string|null $start, DateTimeInterface|string|null $end): string
    {
        return self::short($start).' – '.self::short($end);
    }

    /** @return array{0: int|null, 1: int} */
    private static function parts(DateTimeInterface|string|null $time): array
    {
        if ($time instanceof DateTimeInterface) {
            return [(int) $time->format('G'), (int) $time->format('i')];
        }
        if (! is_string($time) || ! preg_match('/^(\d{1,2}):(\d{2})/', Digits::toLatin($time), $m)) {
            return [null, 0];
        }

        return [(int) $m[1], (int) $m[2]];
    }
}
