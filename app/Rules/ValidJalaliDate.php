<?php

namespace App\Rules;

use App\Support\JalaliDate;
use Closure;
use Illuminate\Contracts\Validation\ValidationRule;

/**
 * تاریخ شمسی معتبر به شکل ۱۴۰۵/۰۸/۲۴. همراه ConvertsJalaliDates در Form Request استفاده شود.
 * new ValidJalaliDate(notFuture: true) — تاریخ آینده قبول نیست (تولد، استخدام …).
 */
class ValidJalaliDate implements ValidationRule
{
    public function __construct(private readonly bool $notFuture = false) {}

    public function validate(string $attribute, mixed $value, Closure $fail): void
    {
        $date = is_string($value) ? JalaliDate::parse($value) : null;

        if ($date === null) {
            $fail('validation.jalali_date')->translate();

            return;
        }

        if ($this->notFuture && $date->isAfter(now(JalaliDate::TIMEZONE)->endOfDay())) {
            $fail('validation.jalali_not_future')->translate();
        }
    }
}
