<?php

namespace App\Support;

/**
 * یکسان‌سازی متن فارسی: حروف عربی ي ى ك به فارسی ی ک، حذف کشیده (ـ).
 * همه‌ی ورودی‌ها از راه NormalizeInput از این می‌گذرند تا جست‌وجوی نام همیشه درست کار کند
 * (کیبورد عربی «علي» می‌نویسد و کیبورد فارسی «علی»).
 */
final class PersianText
{
    private const MAP = [
        'ي' => 'ی',
        'ى' => 'ی',
        'ك' => 'ک',
        'ـ' => '',
    ];

    public static function normalize(string $value): string
    {
        return strtr($value, self::MAP);
    }

    /** آماده‌سازی عبارت جست‌وجو: ارقام لاتین، حروف فارسی، فاصله‌های اضافی حذف. */
    public static function searchTerms(?string $term): array
    {
        $term = trim(self::normalize(Digits::toLatin((string) $term)));

        return $term === '' ? [] : preg_split('/\s+/u', $term);
    }

    /** الگوی LIKE امن (% و _ ورودی کاربر معنی خاص نداشته باشد). */
    public static function like(string $word): string
    {
        return '%'.addcslashes($word, '%_\\').'%';
    }
}
