<?php

namespace App\Enums;

use App\Enums\Concerns\HasLabel;

/** سطح تحصیل معلمان و کارکنان (فهرست ثابت، نه متن آزاد). */
enum EducationLevel: string
{
    use HasLabel;

    case Grade12 = 'grade_12';
    case Grade14 = 'grade_14';
    case Bachelor = 'bachelor';
    case Master = 'master';
    case Doctorate = 'doctorate';
}
