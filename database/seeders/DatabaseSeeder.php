<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;

/**
 * همه‌ی seederها اجرای دوباره را تحمل می‌کنند و داده‌ی موجود را پاک نمی‌کنند.
 * کاربران آزمایشی فقط در محیط local ساخته می‌شوند.
 */
class DatabaseSeeder extends Seeder
{
    public function run(): void
    {
        $this->call([
            RolePermissionSeeder::class,
            SettingsSeeder::class,
            AdminUserSeeder::class,
        ]);

        if (app()->environment('local')) {
            $this->call([DemoUserSeeder::class, DemoSchoolSeeder::class]);
        }
    }
}
