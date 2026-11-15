<?php

namespace App\Http\Middleware;

use App\Support\Digits;
use App\Support\PersianText;
use Illuminate\Foundation\Http\Middleware\TransformsRequest;

/**
 * یکسان‌سازی همه‌ی ورودی‌ها:
 *  - ارقام فارسی و عربی ← لاتین (نام کاربری، نمره، مبلغ، تاریخ، شماره‌ی تماس …)
 *  - حروف عربی ي/ك ← فارسی ی/ک (تا نام‌ها همیشه یک‌شکل ذخیره و جست‌وجو شوند)
 * رمز عبور هم یکسان‌سازی می‌شود؛ چون هم هنگام ساختن و هم هنگام ورود اعمال می‌شود، نتیجه یکی است
 * و کاربری که کیبوردش فارسی مانده، باز هم وارد می‌شود.
 */
class NormalizeInput extends TransformsRequest
{
    protected array $except = ['_token', '_method'];

    protected function transform($key, $value)
    {
        if (! is_string($value) || in_array($key, $this->except, true)) {
            return $value;
        }

        return PersianText::normalize(Digits::toLatin($value));
    }
}
