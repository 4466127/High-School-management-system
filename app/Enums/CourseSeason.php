<?php

namespace App\Enums;

use App\Enums\Concerns\HasLabel;

/** دوره‌ی کورس: زمستانی (رخصتی مکاتب)، در جریان سال تحصیلی، تابستانی. */
enum CourseSeason: string
{
    use HasLabel;

    case Winter = 'winter';
    case SchoolYear = 'school_year';
    case Summer = 'summer';
}
