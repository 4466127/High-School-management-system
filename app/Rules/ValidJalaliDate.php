<?php

namespace App\Rules;

use App\Support\JalaliDate;
use Closure;
use Illuminate\Contracts\Validation\ValidationRule;

/** تاریخ شمسی معتبر به شکل ۱۴۰۵/۰۸/۲۴. همراه ConvertsJalaliDates در Form Request استفاده شود. */
class ValidJalaliDate implements ValidationRule
{
    public function validate(string $attribute, mixed $value, Closure $fail): void
    {
        if (! is_string($value) || JalaliDate::parse($value) === null) {
            $fail('validation.jalali_date')->translate();
        }
    }
}
