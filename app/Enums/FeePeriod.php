<?php

namespace App\Enums;

use App\Enums\Concerns\HasLabel;

/** دوره‌ی فیس: ماهوار (هر ماه یک بل)، سالانه (سالی یک بل)، یک‌بار (ثبت‌نام، کتاب …). */
enum FeePeriod: string
{
    use HasLabel;

    case Monthly = 'monthly';
    case Annual = 'annual';
    case Once = 'once';
}
