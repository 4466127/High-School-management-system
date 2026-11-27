<?php

namespace App\Support;

use Closure;
use Illuminate\Database\Eloquent\Builder;

/**
 * یکسان‌سازی متن فارسی: حروف عربی ي ى ك به فارسی ی ک، حذف کشیده (ـ).
 * همه‌ی ورودی‌ها از راه NormalizeInput از این می‌گذرند تا جستجوی نام همیشه درست کار کند
 * (کیبورد عربی «علي» می‌نویسد و کیبورد فارسی «علی»).
 *
 * جستجو همیشه از whereMatches(): هر واژه‌ی عبارت باید در یکی از ستون‌ها باشد (ترتیب واژه‌ها مهم نیست)،
 * نیم‌فاصله مهم نیست («کتاب‌ها» = «کتابها»)، ارقام فارسی و لاتین یکی است.
 */
final class PersianText
{
    public const ZWNJ = "\u{200C}";

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

    /** آماده‌سازی عبارت جستجو: ارقام لاتین، حروف فارسی، بی نیم‌فاصله؛ جدا با فاصله یا «،». */
    public static function searchTerms(?string $term): array
    {
        $term = str_replace(self::ZWNJ, '', trim(self::normalize(Digits::toLatin((string) $term))));

        return $term === '' ? [] : array_values(array_filter(preg_split('/[\s،,]+/u', $term) ?: [], fn ($w) => $w !== ''));
    }

    /** الگوی LIKE امن (% و _ ورودی کاربر معنی خاص نداشته باشد). */
    public static function like(string $word): string
    {
        return '%'.addcslashes($word, '%_\\').'%';
    }

    /**
     * هر واژه باید در یکی از ستون‌ها باشد. $exact: ستون‌هایی که برابر کامل مقایسه می‌شوند (مثلاً نمبر ثبت)؛
     * $extra: شرط‌های بیشتر برای هر واژه (مثلاً whereHas)، با orWhere… داخل همان گروه.
     *
     * @param  list<string>  $columns
     * @param  list<string>  $exact
     * @param  (Closure(Builder, string): void)|null  $extra
     */
    public static function whereMatches(Builder $query, array $columns, ?string $term, array $exact = [], ?Closure $extra = null): Builder
    {
        foreach (self::searchTerms($term) as $word) {
            $query->where(function (Builder $q) use ($columns, $exact, $extra, $word) {
                foreach ($columns as $column) {
                    self::orLike($q, $column, $word);
                }
                foreach ($exact as $column) {
                    $q->orWhere($q->qualifyColumn($column), $word);
                }
                if ($extra) {
                    $extra($q, $word);
                }
            });
        }

        return $query;
    }

    /** ستون LIKE واژه، بی توجه به نیم‌فاصله‌ی ذخیره‌شده */
    public static function orLike(Builder $q, string $column, string $word): void
    {
        $wrapped = $q->getQuery()->getGrammar()->wrap($q->qualifyColumn($column));
        $q->orWhereRaw("REPLACE({$wrapped}, ?, '') LIKE ?", [self::ZWNJ, self::like($word)]);
    }
}
