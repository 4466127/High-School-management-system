<?php

namespace App\Enums;

use App\Enums\Concerns\HasLabel;

/** نوع اتاق (مرحله‌ی ۱۵). */
enum RoomType: string
{
    use HasLabel;

    case Classroom = 'classroom';
    case Lab = 'lab';
    case Computer = 'computer';
    case Library = 'library';
    case Hall = 'hall';
    case Other = 'other';
}
