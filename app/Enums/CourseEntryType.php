<?php

namespace App\Enums;

use App\Enums\Concerns\HasLabel;

/** نوع سطر دفتر مالی کورس: کمک مردمی، فیس اشتراک (عاید صندوق)، معاش معلم، مصرف کورس. */
enum CourseEntryType: string
{
    use HasLabel;

    case Donation = 'donation';
    case Fee = 'fee';
    case TeacherSalary = 'teacher_salary';
    case Expense = 'expense';

    public function badgeClass(): string
    {
        return match ($this) {
            self::Donation => 'text-bg-success',
            self::Fee => 'text-bg-info',
            self::TeacherSalary => 'text-bg-primary',
            self::Expense => 'text-bg-danger',
        };
    }

    /** عاید صندوق (کمک یا فیس) — نام پرداخت‌کننده دارد */
    public function isIncome(): bool
    {
        return $this === self::Donation || $this === self::Fee;
    }

    /** معاش و کمک مستقیم معلم دارد */
    public function needsTeacher(CourseChannel $channel): bool
    {
        return $this === self::TeacherSalary || $channel === CourseChannel::DirectToTeacher;
    }
}
