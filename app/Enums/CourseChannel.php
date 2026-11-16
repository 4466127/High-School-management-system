<?php

namespace App\Enums;

use App\Enums\Concerns\HasLabel;

/** مسیر پول کورس: صندوق کورس (نزد مکتب) یا مستقیم به معلم (فقط کمک؛ صندوق را تغییر نمی‌دهد). */
enum CourseChannel: string
{
    use HasLabel;

    case Cashbox = 'cashbox';
    case DirectToTeacher = 'direct_to_teacher';
}
