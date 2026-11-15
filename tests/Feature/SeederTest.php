<?php

namespace Tests\Feature;

use App\Models\Permission;
use App\Models\Role;
use App\Models\Setting;
use App\Models\Staff;
use App\Models\Student;
use App\Models\User;
use Database\Seeders\AdminUserSeeder;
use Database\Seeders\DemoSchoolSeeder;
use Database\Seeders\RolePermissionSeeder;
use Database\Seeders\SettingsSeeder;
use Tests\TestCase;

class SeederTest extends TestCase
{
    public function test_six_roles_and_all_permissions_exist(): void
    {
        $this->assertEqualsCanonicalizing(
            ['admin', 'principal', 'office', 'teacher', 'accountant', 'course_manager'],
            Role::pluck('key')->all(),
        );
        $this->assertSame(count(config('permissions.permissions')), Permission::count());
        $this->assertSame('مدیر مکتب', Role::where('key', 'principal')->value('name'));
    }

    public function test_seeders_can_run_again_without_duplicates_or_overwriting(): void
    {
        Setting::put('school_name', 'لیسه‌ی آزمایشی');

        $this->seed([RolePermissionSeeder::class, SettingsSeeder::class]);

        $this->assertSame(6, Role::count());
        $this->assertSame(count(config('permissions.permissions')), Permission::count());
        $this->assertSame('لیسه‌ی آزمایشی', Setting::get('school_name'), 'Re-seeding must not overwrite school settings');
    }

    public function test_default_grading_settings(): void
    {
        $this->assertSame('40', (string) Setting::get('pass_percent'));
        $this->assertFalse(Setting::enabled('fees_enabled'));
        $this->assertSame('AFN', Setting::get('currency'));
    }

    public function test_demo_school_seeder_creates_unique_records_once(): void
    {
        $this->seed(DemoSchoolSeeder::class);

        $count = Student::count();
        $this->assertGreaterThanOrEqual(30, $count);
        $this->assertSame($count, Student::distinct()->count('admission_no'));
        $this->assertSame(15, Staff::count());

        $this->seed(DemoSchoolSeeder::class); // دوباره: هیچ کاری نکند
        $this->assertSame($count, Student::count());
    }

    public function test_admin_seeder_creates_one_admin_only_once(): void
    {
        $this->seed(AdminUserSeeder::class);
        $this->seed(AdminUserSeeder::class);

        $admins = User::whereHas('roles', fn ($q) => $q->where('key', 'admin'))->get();
        $this->assertCount(1, $admins);
        $this->assertTrue($admins->first()->must_change_password);
    }
}
