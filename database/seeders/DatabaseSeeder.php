<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;

/**
 * همه‌ی seederها اجرای دوباره را تحمل می‌کنند و داده‌ی موجود را پاک نمی‌کنند.
 * داده‌ی آزمایشی (کاربران demo.*، دانش‌آموزان، سال ۱۴۰۵) فقط در محیط local ساخته می‌شود.
 */
class DatabaseSeeder extends Seeder
{
    public function run(): void
    {
        $this->call([
            RolePermissionSeeder::class,
            SettingsSeeder::class,
            GradeLevelSeeder::class,
            SubjectSeeder::class,
            ExamTypeSeeder::class,
            AdminUserSeeder::class,
        ]);

        if (app()->environment('local')) {
            $this->call([DemoUserSeeder::class, DemoSchoolSeeder::class, DemoAcademicSeeder::class, DemoAttendanceSeeder::class, DemoExamSeeder::class]);
        }
    }
}
