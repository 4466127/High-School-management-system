<?php

namespace App\Http\Middleware;

use App\Support\Digits;
use Illuminate\Foundation\Http\Middleware\TransformsRequest;

/** ارقام فارسی و عربی همه‌ی ورودی‌ها به لاتین تبدیل می‌شود (نام کاربری، نمره، مبلغ، تاریخ …). */
class NormalizeDigits extends TransformsRequest
{
    protected array $except = ['_token', '_method'];

    protected function transform($key, $value)
    {
        if (! is_string($value) || in_array($key, $this->except, true)) {
            return $value;
        }

        return Digits::toLatin($value);
    }
}
