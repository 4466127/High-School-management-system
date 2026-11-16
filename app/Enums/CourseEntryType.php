<?php

namespace App\Enums;

use App\Enums\Concerns\HasLabel;

/** نوع سطر دفتر مالی کورس: کمک مردمی، معاش معلم، مصرف کورس. */
enum CourseEntryType: string
{
    use HasLabel;

    case Donation = 'donation';
    case TeacherSalary = 'teacher_salary';
    case Expense = 'expense';

    public function badgeClass(): string
    {
        return match ($this) {
            self::Donation => 'text-bg-success',
            self::TeacherSalary => 'text-bg-primary',
            self::Expense => 'text-bg-danger',
        };
    }

    /** معاش و کمک مستقیم معلم دارد */
    public function needsTeacher(CourseChannel $channel): bool
    {
        return $this === self::TeacherSalary || $channel === CourseChannel::DirectToTeacher;
    }
}
