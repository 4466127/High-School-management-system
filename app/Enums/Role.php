<?php

namespace App\Enums;

/** نه نقش ثابت سیستم (کتابدار از مرحله‌ی ۱۷، مسئول لابراتوار از ۱۸، سرمعلم از 1.8.1) (ARCHITECTURE.md بخش ۶). مجوزهای هر نقش در config/permissions.php است. */
enum Role: string
{
    case Admin = 'admin';
    case Principal = 'principal';
    case HeadTeacher = 'head_teacher';
    case Office = 'office';
    case Teacher = 'teacher';
    case Accountant = 'accountant';
    case CourseManager = 'course_manager';
    case Librarian = 'librarian';
    case LabManager = 'lab_manager';

    public function label(): string
    {
        return __('roles.'.$this->value);
    }
}
