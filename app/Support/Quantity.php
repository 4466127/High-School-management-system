<?php

namespace App\Support;

/**
 * مقدار موجودی لابراتوار (مرحله‌ی ۱۸): «۲۵۰»، «۱٫۵»، «0.75». حساب داخلی با صدم (عدد صحیح) تا خطای float پیش نیاید؛
 * پایگاه داده DECIMAL(12,2). ورودی کاربر با parse() بررسی می‌شود.
 */
final class Quantity
{
    /** متن کاربر ← صدم؛ null = نادرست (منفی، بیشتر از دو رقم اعشار، یا متن) */
    public static function parse(?string $value): ?int
    {
        $clean = str_replace([',', '٬', ' '], '', Digits::toLatin(trim((string) $value)));
        $clean = str_replace('٫', '.', $clean);
        if ($clean === '' || ! preg_match('/^\d{1,9}(\.\d{1,2})?$/', $clean)) {
            return null;
        }
        [$int, $dec] = array_pad(explode('.', $clean), 2, '');

        return (int) $int * 100 + (int) str_pad($dec, 2, '0');
    }

    /** مقدار پایگاه داده («12.50») ← صدم */
    public static function toHundredths(string|int|null $value): int
    {
        $value = (string) ($value ?? '0');
        $negative = str_starts_with($value, '-');
        $parsed = self::parse(ltrim($value, '-')) ?? 0;

        return $negative ? -$parsed : $parsed;
    }

    /** صدم ← «12.50» برای ذخیره */
    public static function fromHundredths(int $h): string
    {
        $sign = $h < 0 ? '-' : '';
        $h = abs($h);

        return $sign.intdiv($h, 100).'.'.str_pad((string) ($h % 100), 2, '0', STR_PAD_LEFT);
    }

    /** نمایش: «۱۲٫۵»، «۲۵۰» (بی صفرهای اضافه) */
    public static function format(string|int|null $value): string
    {
        $h = self::toHundredths($value);
        $text = self::fromHundredths($h);
        $text = str_contains($text, '.') ? rtrim(rtrim($text, '0'), '.') : $text;

        return Digits::toPersian(str_replace('.', '٫', $text));
    }

    /** برای خانه‌ی فرم (ارقام لاتین، بی صفر اضافه) */
    public static function plain(string|int|null $value): string
    {
        $text = self::fromHundredths(self::toHundredths($value));

        return rtrim(rtrim($text, '0'), '.');
    }
}
