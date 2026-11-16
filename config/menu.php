<?php

/*
|--------------------------------------------------------------------------
| منوی اصلی
|--------------------------------------------------------------------------
| هر مورد فقط وقتی دیده می‌شود که کاربر دست‌کم یکی از مجوزهای «can» را داشته باشد.
| «route» = null یعنی صفحه هنوز ساخته نشده (در منو با نشان «به‌زودی»).
| «feature» = نام یک ویژگی قابل خاموش‌کردن در تنظیمات (App\Support\Menu).
| نام فارسی هر مورد: lang/fa/menu.php
*/

return [
    ['key' => 'dashboard', 'icon' => 'fa-gauge-high', 'route' => 'dashboard', 'can' => ['dashboard.view']],
    ['key' => 'students', 'icon' => 'fa-user-graduate', 'route' => 'students.index', 'can' => ['students.view']],
    ['key' => 'staff', 'icon' => 'fa-chalkboard-user', 'route' => 'staff.index', 'can' => ['staff.manage']],
    ['key' => 'academics', 'icon' => 'fa-school', 'route' => null, 'can' => ['academics.view', 'academics.manage'], 'children' => [
        ['key' => 'sections', 'icon' => 'fa-chalkboard', 'route' => 'academics.sections.index', 'can' => ['academics.view', 'academics.manage']],
        ['key' => 'years', 'icon' => 'fa-calendar-days', 'route' => 'academics.years.index', 'can' => ['academics.manage']],
        ['key' => 'grades', 'icon' => 'fa-book-open', 'route' => 'academics.grades.index', 'can' => ['academics.manage']],
        ['key' => 'promotion', 'icon' => 'fa-arrow-up-wide-short', 'route' => 'academics.promotion.create', 'can' => ['students.manage']],
    ]],
    ['key' => 'attendance', 'icon' => 'fa-clipboard-check', 'route' => null, 'can' => ['attendance.take', 'attendance.edit', 'reports.academic', 'staff.manage'], 'children' => [
        ['key' => 'attendance_daily', 'icon' => 'fa-clipboard-check', 'route' => 'attendance.daily.index', 'can' => ['attendance.take', 'attendance.edit', 'reports.academic']],
        ['key' => 'attendance_monthly', 'icon' => 'fa-calendar-days', 'route' => 'attendance.monthly.index', 'can' => ['attendance.take', 'attendance.edit', 'reports.academic']],
        ['key' => 'attendance_absentees', 'icon' => 'fa-user-clock', 'route' => 'attendance.absentees.index', 'can' => ['attendance.take', 'attendance.edit', 'reports.academic']],
        ['key' => 'attendance_staff', 'icon' => 'fa-id-badge', 'route' => 'attendance.staff.index', 'can' => ['staff.manage']],
    ]],
    ['key' => 'exams', 'icon' => 'fa-file-pen', 'route' => null, 'can' => ['marks.enter', 'exams.manage', 'reportcards.print'], 'children' => [
        ['key' => 'exams_marks', 'icon' => 'fa-pen-to-square', 'route' => 'exams.marks.index', 'can' => ['marks.enter']],
        ['key' => 'exams_results', 'icon' => 'fa-table-list', 'route' => 'exams.results.index', 'can' => ['exams.manage', 'reportcards.print']],
        ['key' => 'exams_reportcards', 'icon' => 'fa-id-card', 'route' => 'exams.reportcards.index', 'can' => ['exams.manage', 'reportcards.print']],
        ['key' => 'exams_manage', 'icon' => 'fa-list-check', 'route' => 'exams.manage.index', 'can' => ['exams.manage']],
    ]],
    ['key' => 'finance', 'icon' => 'fa-coins', 'route' => null, 'can' => ['finance.view', 'finance.manage'], 'feature' => 'finance'],
    ['key' => 'courses', 'icon' => 'fa-people-group', 'route' => null, 'can' => ['courses.manage', 'courses.attendance', 'courses.ledger']],
    ['key' => 'reports', 'icon' => 'fa-chart-column', 'route' => null, 'can' => ['reports.academic', 'reports.finance']],
    ['key' => 'settings', 'icon' => 'fa-gear', 'route' => null, 'can' => ['settings.manage', 'users.manage', 'activity_logs.view', 'backups.manage', 'students.manage', 'staff.manage'], 'children' => [
        ['key' => 'school', 'icon' => 'fa-building-columns', 'route' => 'settings.school.edit', 'can' => ['settings.manage']],
        ['key' => 'users', 'icon' => 'fa-users-gear', 'route' => 'settings.users.index', 'can' => ['users.manage']],
        ['key' => 'activity_logs', 'icon' => 'fa-clock-rotate-left', 'route' => 'settings.activity-logs.index', 'can' => ['activity_logs.view']],
        ['key' => 'import', 'icon' => 'fa-file-excel', 'route' => 'import.index', 'can' => ['students.manage', 'staff.manage']],
        ['key' => 'backups', 'icon' => 'fa-database', 'route' => null, 'can' => ['backups.manage']],
    ]],
];
