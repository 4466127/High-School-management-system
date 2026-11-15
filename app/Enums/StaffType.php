<?php

namespace App\Enums;

use App\Enums\Concerns\HasLabel;

enum StaffType: string
{
    use HasLabel;

    case Teacher = 'teacher';
    case Admin = 'admin';
    case Service = 'service';
    case CourseTeacher = 'course_teacher';
}
