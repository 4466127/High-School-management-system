<?php

namespace App\Enums;

use App\Enums\Concerns\HasLabel;

/** نوع مکتوب (دسته‌بندی، جست‌وجو و قالب‌ها). شکل و متن هر نوع در قالب‌های قابل ویرایش است، نه در کد. */
enum LetterType: string
{
    use HasLabel;

    case General = 'general';
    case StudentIntroduction = 'student_introduction';
    case EnrollmentConfirmation = 'enrollment_confirmation';
    case Transfer = 'transfer';
    case Recommendation = 'recommendation';
    case Certificate = 'certificate';
    case EmploymentConfirmation = 'employment_confirmation';
    case Invitation = 'invitation';
}
