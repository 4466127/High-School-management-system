<?php

/*
|--------------------------------------------------------------------------
| نقش‌ها و مجوزها
|--------------------------------------------------------------------------
| منبع اصلی نقش‌ها و مجوزها. RolePermissionSeeder این‌ها را با پایگاه داده
| هماهنگ می‌کند (اجرای دوباره امن است). نام فارسی هر مورد در lang/fa است.
| مطابق جدول دسترسی ARCHITECTURE.md بخش ۶.
|
| نقش «admin» (مدیر سیستم) همه‌ی مجوزها را دارد و در grants نیامده است.
| محدوده‌ی «فقط صنف/مضمون خودش» برای معلم با Policy در ماژول‌های بعدی اعمال می‌شود.
*/

return [

    'admin_role' => 'admin',

    // کلید مجوز => گروه (برای نمایش گروه‌بندی‌شده)
    'permissions' => [
        'dashboard.view' => 'general',

        'students.view' => 'students',
        'students.view_all' => 'students',
        'students.manage' => 'students',

        'staff.manage' => 'staff',

        'academics.view' => 'academics',
        'academics.manage' => 'academics',
        'timetable.manage' => 'academics',
        'teaching.plan' => 'academics',
        'teaching.manage' => 'academics',

        'attendance.take' => 'attendance',
        'attendance.edit' => 'attendance',

        'marks.enter' => 'exams',
        'exams.manage' => 'exams',
        'reportcards.print' => 'exams',

        'finance.view' => 'finance',
        'finance.manage' => 'finance',

        'courses.manage' => 'courses',
        'courses.attendance' => 'courses',
        'courses.ledger' => 'courses',

        'reports.academic' => 'reports',
        'reports.finance' => 'reports',

        'library.view' => 'library',
        'library.manage' => 'library',

        'labs.view' => 'labs',
        'labs.activity' => 'labs',
        'labs.manage' => 'labs',

        'announcements.manage' => 'communication',
        'letters.manage' => 'communication',

        'settings.manage' => 'system',
        'users.manage' => 'system',
        'backups.manage' => 'system',
        'license.manage' => 'system',
        'activity_logs.view' => 'system',
    ],

    'grants' => [
        'principal' => [
            'dashboard.view',
            'students.view', 'students.view_all', 'students.manage',
            'staff.manage',
            'academics.view', 'academics.manage', 'timetable.manage', 'teaching.plan', 'teaching.manage',
            'attendance.take', 'attendance.edit',
            'marks.enter', 'exams.manage', 'reportcards.print',
            'finance.view', 'finance.manage',
            'courses.manage', 'courses.attendance', 'courses.ledger',
            'reports.academic', 'reports.finance',
            'library.view', 'library.manage',
            'labs.view', 'labs.activity', 'labs.manage',
            'announcements.manage', 'letters.manage',
            'settings.manage',
        ],

        'office' => [
            'dashboard.view',
            'students.view', 'students.view_all', 'students.manage',
            'staff.manage',
            'academics.view', 'academics.manage', 'timetable.manage', 'teaching.manage',
            'attendance.take', 'attendance.edit',
            'reportcards.print',
            'reports.academic',
            'library.view',
            'labs.view',
            'announcements.manage', 'letters.manage',
        ],

        'teacher' => [
            'dashboard.view',
            'students.view',
            'academics.view', 'teaching.plan',
            'attendance.take',
            'marks.enter',
            'reportcards.print',
            'courses.attendance',
            'reports.academic',
            'library.view',
            'labs.view', 'labs.activity',
        ],

        'accountant' => [
            'dashboard.view',
            'students.view', 'students.view_all',
            'finance.view', 'finance.manage',
            'courses.ledger',
            'reports.finance',
        ],

        'course_manager' => [
            'dashboard.view',
            'students.view', 'students.view_all',
            'courses.manage', 'courses.attendance', 'courses.ledger',
            'reports.finance',
        ],

        // کتابدار (مرحله‌ی ۱۷): فهرست کتاب‌ها، جلدها، امانت و کتاب درسی؛ دانش‌آموزان فقط برای امانت دیده می‌شوند
        'librarian' => [
            'dashboard.view',
            'students.view', 'students.view_all',
            'library.view', 'library.manage',
        ],

        // مسئول لابراتوار (مرحله‌ی ۱۸): موجودی و دفتر، لابراتوارها، همه‌ی فعالیت‌های عملی و گزارش
        'lab_manager' => [
            'dashboard.view',
            'labs.view', 'labs.activity', 'labs.manage',
        ],
    ],

];
