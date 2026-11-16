<?php

// برچسب فارسی enumها (App\Enums\Concerns\HasLabel)
return [
    'gender' => [
        'male' => 'پسر / مرد',
        'female' => 'دختر / زن',
    ],
    'gender_student' => [
        'male' => 'پسر',
        'female' => 'دختر',
    ],
    'gender_staff' => [
        'male' => 'مرد',
        'female' => 'زن',
    ],
    'education_level' => [
        'grade_12' => 'دوازده‌پاس',
        'grade_14' => 'چهارده‌پاس',
        'bachelor' => 'کارشناسی (لیسانس)',
        'master' => 'کارشناسی ارشد (ماستری)',
        'doctorate' => 'دکترا',
    ],
    'student_status' => [
        'active' => 'فعال',
        'inactive' => 'غیرفعال',
        'transferred' => 'منتقل‌شده',
        'dropped' => 'ترک تحصیل',
        'graduated' => 'فارغ',
    ],
    'shift' => [
        'morning' => 'صبح',
        'afternoon' => 'بعدازظهر',
    ],
    'enrollment_status' => [
        'active' => 'جاری',
        'promoted' => 'ارتقا',
        'repeated' => 'تکرار صنف',
        'graduated' => 'فارغ',
        'transferred' => 'منتقل‌شده',
        'dropped' => 'ترک تحصیل',
    ],
    'school_type' => [
        'government' => 'دولتی',
        'private' => 'خصوصی',
    ],
    'staff_type' => [
        'teacher' => 'معلم',
        'admin' => 'کارمند اداری',
        'service' => 'کارمند خدماتی',
        'course_teacher' => 'معلم کورس',
    ],
    'attendance_status' => [
        'present' => 'حاضر',
        'late' => 'تأخیر',
        'absent' => 'غیرحاضر',
        'excused' => 'رخصت',
        'sick' => 'مریض',
    ],
    'attendance_status_code' => [
        'present' => 'ح',
        'late' => 'ت',
        'absent' => 'غ',
        'excused' => 'ر',
        'sick' => 'م',
    ],
    'exam_status' => [
        'draft' => 'پیش‌نویس',
        'open' => 'باز (ثبت نمره)',
        'locked' => 'قفل',
    ],
    'final_result' => [
        'passed' => 'کامیاب',
        'conditional' => 'مشروط',
        'failed' => 'ناکام',
        'deprived' => 'محروم',
    ],
    'employment_status' => [
        'active' => 'برسر وظیفه',
        'on_leave' => 'رخصتی',
        'left' => 'منفک / رفته',
    ],
];
