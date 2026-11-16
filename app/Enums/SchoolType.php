<?php

namespace App\Enums;

use App\Enums\Concerns\HasLabel;
use App\Models\Setting;

enum SchoolType: string
{
    use HasLabel;

    case Government = 'government';
    case Private = 'private';

    /** نوع مکتب از تنظیمات (پیش‌فرض دولتی) */
    public static function current(): self
    {
        return self::tryFrom((string) Setting::get('school_type')) ?? self::Government;
    }
}
