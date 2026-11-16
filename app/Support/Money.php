<?php

namespace App\Support;

/**
 * پول افغانی. حساب داخلی با «پول» (سنت، عدد صحیح) تا خطای اعشاری float پیش نیاید؛ پایگاه داده DECIMAL(12,2).
 */
final class Money
{
    /** «1,250.5» یا «۱٬۲۵۰٫۵» یا 1250.5 ← 125050 */
    public static function toCents(string|int|float|null $value): int
    {
        $value = str_replace([',', '٬', ' ', "\u{066C}"], '', Digits::toLatin((string) ($value ?? '0')));

        return (int) round(((float) $value) * 100);
    }

    /** 125050 ← "1250.50" (برای ذخیره) */
    public static function fromCents(int $cents): string
    {
        return number_format($cents / 100, 2, '.', '');
    }

    /** نمایش: «۱٬۲۵۰ افغانی» (اعشار فقط اگر باشد) */
    public static function format(string|int|float|null $amount, bool $currency = true): string
    {
        $cents = self::toCents($amount);
        $text = number_format(abs($cents) / 100, $cents % 100 === 0 ? 0 : 2, '٫', '٬');

        return ($cents < 0 ? '−' : '').Digits::toPersian($text).($currency ? ' '.__('finance.afn') : '');
    }

    /** عدد ساده برای CSV: 1250.5 */
    public static function plain(string|int|float|null $amount): string
    {
        return rtrim(rtrim(self::fromCents(self::toCents($amount)), '0'), '.');
    }
}
