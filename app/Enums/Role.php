<?php

namespace App\Enums;

/** هفت نقش ثابت سیستم (کتابدار از مرحله‌ی ۱۷) (ARCHITECTURE.md بخش ۶). مجوزهای هر نقش در config/permissions.php است. */
enum Role: string
{
    case Admin = 'admin';
    case Principal = 'principal';
    case Office = 'office';
    case Teacher = 'teacher';
    case Accountant = 'accountant';
    case CourseManager = 'course_manager';
    case Librarian = 'librarian';

    public function label(): string
    {
        return __('roles.'.$this->value);
    }
}
