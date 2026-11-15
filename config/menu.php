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
    ['key' => 'academics', 'icon' => 'fa-school', 'route' => null, 'can' => ['academics.view', 'academics.manage']],
    ['key' => 'attendance', 'icon' => 'fa-clipboard-check', 'route' => null, 'can' => ['attendance.take', 'attendance.edit']],
    ['key' => 'exams', 'icon' => 'fa-file-pen', 'route' => null, 'can' => ['marks.enter', 'exams.manage', 'reportcards.print']],
    ['key' => 'finance', 'icon' => 'fa-coins', 'route' => null, 'can' => ['finance.view', 'finance.manage'], 'feature' => 'finance'],
    ['key' => 'courses', 'icon' => 'fa-people-group', 'route' => null, 'can' => ['courses.manage', 'courses.attendance', 'courses.ledger']],
    ['key' => 'reports', 'icon' => 'fa-chart-column', 'route' => null, 'can' => ['reports.academic', 'reports.finance']],
    ['key' => 'settings', 'icon' => 'fa-gear', 'route' => null, 'can' => ['settings.manage', 'users.manage', 'activity_logs.view', 'backups.manage'], 'children' => [
        ['key' => 'school', 'icon' => 'fa-building-columns', 'route' => null, 'can' => ['settings.manage']],
        ['key' => 'users', 'icon' => 'fa-users-gear', 'route' => 'settings.users.index', 'can' => ['users.manage']],
        ['key' => 'activity_logs', 'icon' => 'fa-clock-rotate-left', 'route' => 'settings.activity-logs.index', 'can' => ['activity_logs.view']],
        ['key' => 'backups', 'icon' => 'fa-database', 'route' => null, 'can' => ['backups.manage']],
    ]],
];
