<?php

namespace App\Enums;

use App\Enums\Concerns\HasLabel;

/** امانت‌گیرنده (مرحله‌ی ۱۷). */
enum BorrowerType: string
{
    use HasLabel;

    case Student = 'student';
    case Staff = 'staff';
}
