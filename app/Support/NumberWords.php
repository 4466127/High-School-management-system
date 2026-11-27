<?php

namespace App\Support;

/**
 * عدد به حروف دری (برای ستون «به حروف» فورم‌های وزارت معارف): ۸۷ ← «هشتاد و هفت»، ۸۷٫۵ ← «هشتاد و هفت و نیم»،
 * ۱۲۴۵ ← «یک هزار و دوصد و چهل و پنج». فقط عددهای مثبت تا ۹۹۹٬۹۹۹ (نمره و مجموع نمره).
 */
final class NumberWords
{
    private const ONES = ['صفر', 'یک', 'دو', 'سه', 'چهار', 'پنج', 'شش', 'هفت', 'هشت', 'نه'];

    private const TEENS = ['ده', 'یازده', 'دوازده', 'سیزده', 'چهارده', 'پانزده', 'شانزده', 'هفده', 'هژده', 'نزده'];

    private const TENS = [2 => 'بیست', 3 => 'سی', 4 => 'چهل', 5 => 'پنجاه', 6 => 'شصت', 7 => 'هفتاد', 8 => 'هشتاد', 9 => 'نود'];

    private const HUNDREDS = [1 => 'یکصد', 2 => 'دوصد', 3 => 'سه‌صد', 4 => 'چهارصد', 5 => 'پنجصد', 6 => 'ششصد', 7 => 'هفتصد', 8 => 'هشتصد', 9 => 'نهصد'];

    public static function fa(int|float|string|null $number): string
    {
        if ($number === null || $number === '') {
            return '';
        }
        $number = round((float) Digits::toLatin((string) $number), 2);
        if ($number < 0 || $number >= 1_000_000) {
            return Digits::toPersian((string) $number);
        }
        $whole = (int) floor($number);
        $fraction = (int) round(($number - $whole) * 100);
        $words = self::integer($whole);
        if ($fraction === 50) {
            return $words.' و نیم';
        }
        if ($fraction > 0) {
            return $words.' ممیز '.self::integer($fraction % 10 === 0 ? intdiv($fraction, 10) : $fraction);
        }

        return $words;
    }

    private static function integer(int $n): string
    {
        if ($n < 10) {
            return self::ONES[$n];
        }
        $parts = [];
        if ($n >= 1000) {
            $parts[] = self::integer(intdiv($n, 1000)).' هزار';
            $n %= 1000;
        }
        if ($n >= 100) {
            $parts[] = self::HUNDREDS[intdiv($n, 100)];
            $n %= 100;
        }
        if ($n >= 20) {
            $parts[] = self::TENS[intdiv($n, 10)];
            $n %= 10;
            if ($n > 0) {
                $parts[] = self::ONES[$n];
            }
        } elseif ($n >= 10) {
            $parts[] = self::TEENS[$n - 10];
        } elseif ($n > 0) {
            $parts[] = self::ONES[$n];
        }

        return implode(' و ', $parts);
    }
}
