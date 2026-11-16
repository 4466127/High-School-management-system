<?php

namespace Tests\Feature\System;

use App\Enums\Role;
use App\Models\AcademicYear;
use App\Models\GradeLevel;
use App\Models\Mark;
use App\Models\Role as RoleModel;
use App\Models\Setting;
use App\Models\Student;
use App\Models\User;
use App\Services\BackupService;
use App\Services\ResetService;
use App\Services\SystemCheckService;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;

class ResetAndCheckTest extends SystemTestCase
{
    public function test_data_reset_keeps_users_settings_and_structure(): void
    {
        $this->enterAllMarks();
        Setting::put('school_name', 'لیسه‌ی آزمایشی');
        Storage::disk('local')->put('school/logo.png', 'logo');
        Setting::put('logo_path', 'school/logo.png');
        Storage::disk('local')->put('students/photos/a.jpg', 'x');
        $demo = User::factory()->withRole(Role::Teacher)->create(['username' => 'demo.teacher']);
        $grades = GradeLevel::count();

        $r = app(ResetService::class)->run('data', backup: false);

        $this->assertGreaterThan(10, $r['tables']);
        $this->assertSame(0, Student::count());
        $this->assertSame(0, Mark::count());
        $this->assertSame(0, AcademicYear::count());
        $this->assertTrue(User::whereKey($this->principal->id)->exists());
        $this->assertFalse(User::whereKey($demo->id)->exists());
        $this->assertSame(0, DB::table('role_user')->where('user_id', $demo->id)->count());
        $this->assertSame('لیسه‌ی آزمایشی', Setting::get('school_name'));
        $this->assertSame($grades, GradeLevel::count());
        $this->assertTrue(Storage::disk('local')->exists('school/logo.png'));
        $this->assertFalse(Storage::disk('local')->exists('students/photos/a.jpg'));
        $this->actingAs($this->principal)->get('/')->assertOk();
    }

    public function test_full_reset_is_like_a_fresh_install(): void
    {
        Setting::put('school_name', 'لیسه‌ی آزمایشی');

        $r = app(ResetService::class)->run('full', backup: false);

        $this->assertSame(0, Student::count());
        $this->assertSame(1, User::count());
        $this->assertSame('admin', $r['admin']['username']);
        $admin = User::firstWhere('username', 'admin');
        $this->assertTrue($admin->must_change_password);
        $this->assertTrue($admin->hasRole(Role::Admin));
        $this->assertSame(config('school.defaults.school_name'), Setting::get('school_name'));
        $this->assertGreaterThan(0, GradeLevel::count());
        $this->assertGreaterThan(0, RoleModel::count());
    }

    public function test_reset_command_needs_database_name_and_takes_backup(): void
    {
        $this->artisan('school:reset', ['--data' => true, '--confirm' => 'maktab'])
            ->expectsOutputToContain(__('backup.cli.cancelled'))->assertExitCode(0);
        $this->assertGreaterThan(0, Student::count());
        $this->assertTrue(app(BackupService::class)->all()->isEmpty());

        $this->artisan('school:reset')
            ->expectsQuestion(__('backup.reset.ask'), '1')
            ->expectsQuestion(__('backup.cli.confirm', ['db' => 'maktab_test']), 'maktab_test')
            ->expectsOutputToContain(__('backup.reset.done', ['n' => count(app(ResetService::class)->tablesToClear('data'))]))
            ->assertExitCode(0);
        $this->assertSame(0, Student::count());
        $this->assertSame('pre_reset', app(BackupService::class)->latest()['reason']);
    }

    public function test_check_reports_and_fixes_missing_admin(): void
    {
        $items = collect(app(SystemCheckService::class)->run())->keyBy('key');
        $this->assertSame('ok', $items['database']['level']);
        $this->assertSame('ok', $items['tables']['level']);
        $this->assertSame('ok', $items['migrations']['level']);
        $this->assertSame('warn', $items['backup']['level']);
        $this->assertSame('backup', $items['backup']['fix']);

        RoleModel::where('key', Role::Admin->value)->first()->users()->update(['is_active' => false]);
        $items = collect(app(SystemCheckService::class)->run())->keyBy('key');
        $this->assertSame('error', $items['admin']['level']);

        $this->artisan('school:check')->assertExitCode(1);
        $this->artisan('school:check', ['--fix' => true])
            ->expectsOutputToContain(__('backup.check.fix_seed'))
            ->expectsOutputToContain(__('backup.check.fix_backup_new', ['file' => '']))
            ->assertExitCode(0);
        $this->assertTrue(RoleModel::where('key', Role::Admin->value)->first()->users()->where('is_active', true)->exists());
        $this->assertFalse(app(BackupService::class)->isDue());
    }
}
