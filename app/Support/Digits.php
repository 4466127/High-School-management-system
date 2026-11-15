<?php

namespace App\Support;

/** تبدیل ارقام فارسی/عربی و لاتین. */
final class Digits
{
    private const PERSIAN = ['۰', '۱', '۲', '۳', '۴', '۵', '۶', '۷', '۸', '۹'];

    private const ARABIC = ['٠', '١', '٢', '٣', '٤', '٥', '٦', '٧', '٨', '٩'];

    private const LATIN = ['0', '1', '2', '3', '4', '5', '6', '7', '8', '9'];

    /** ارقام فارسی و عربی ← لاتین؛ ممیز فارسی (٫) ← نقطه. */
    public static function toLatin(string $value): string
    {
        $value = str_replace(self::PERSIAN, self::LATIN, $value);
        $value = str_replace(self::ARABIC, self::LATIN, $value);

        return str_replace('٫', '.', $value);
    }

    /** ارقام لاتین ← فارسی (برای نمایش). */
    public static function toPersian(string|int|float|null $value): string
    {
        return str_replace(self::LATIN, self::PERSIAN, (string) $value);
    }
}
