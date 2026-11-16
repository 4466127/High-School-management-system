<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;

/**
 * همه‌ی seederها اجرای دوباره را تحمل می‌کنند و داده‌ی موجود را پاک نمی‌کنند.
 * داده‌ی آزمایشی (کاربران demo.*، دانش‌آموزان، سال ۱۴۰۵) فقط در محیط local ساخته می‌شود.
 */
class DatabaseSeeder extends Seeder
{
    /** ساختار پایه‌ی هر نصب (بدون کاربر و داده‌ی آزمایشی)؛ ResetService هم همین را به کار می‌برد */
    public const BASE = [
        RolePermissionSeeder::class,
        SettingsSeeder::class,
        GradeLevelSeeder::class,
        SubjectSeeder::class,
        ExamTypeSeeder::class,
        FinanceSeeder::class,
    ];

    public function run(): void
    {
        $this->call([...self::BASE, AdminUserSeeder::class]);

        if (app()->environment('local')) {
            $this->call([DemoUserSeeder::class, DemoSchoolSeeder::class, DemoAcademicSeeder::class, DemoAttendanceSeeder::class, DemoExamSeeder::class, DemoFinanceSeeder::class, DemoCourseSeeder::class]);
        }
    }
}
