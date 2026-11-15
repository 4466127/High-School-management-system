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

        'settings.manage' => 'system',
        'users.manage' => 'system',
        'backups.manage' => 'system',
        'activity_logs.view' => 'system',
    ],

    'grants' => [
        'principal' => [
            'dashboard.view',
            'students.view', 'students.view_all', 'students.manage',
            'staff.manage',
            'academics.view', 'academics.manage',
            'attendance.take', 'attendance.edit',
            'marks.enter', 'exams.manage', 'reportcards.print',
            'finance.view', 'finance.manage',
            'courses.manage', 'courses.attendance', 'courses.ledger',
            'reports.academic', 'reports.finance',
            'settings.manage',
        ],

        'office' => [
            'dashboard.view',
            'students.view', 'students.view_all', 'students.manage',
            'staff.manage',
            'academics.view', 'academics.manage',
            'attendance.take', 'attendance.edit',
            'reportcards.print',
            'reports.academic',
        ],

        'teacher' => [
            'dashboard.view',
            'students.view',
            'academics.view',
            'attendance.take',
            'marks.enter',
            'reportcards.print',
            'courses.attendance',
            'reports.academic',
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
    ],

];
