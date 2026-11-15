<?php

namespace App\Enums;

/** شش نقش ثابت سیستم (ARCHITECTURE.md بخش ۶). مجوزهای هر نقش در config/permissions.php است. */
enum Role: string
{
    case Admin = 'admin';
    case Principal = 'principal';
    case Office = 'office';
    case Teacher = 'teacher';
    case Accountant = 'accountant';
    case CourseManager = 'course_manager';

    public function label(): string
    {
        return __('roles.'.$this->value);
    }
}
