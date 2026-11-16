<?php

namespace Tests\Feature\Settings;

use App\Enums\Role;
use App\Models\ActivityLog;
use App\Models\Setting;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

class SchoolSettingsTest extends TestCase
{
    private function payload(array $overrides = []): array
    {
        return array_merge([
            'school_name' => 'لیسه‌ی عالی آزمایشی',
            'school_type' => 'private',
            'province' => 'هرات',
            'district' => 'ناحیه‌ی سوم',
            'principal_name' => 'عبدالرحیم',
            'fees_enabled' => '1',
            'pass_percent' => '50',
            'conditional_max_failed_subjects' => '3',
            'deprivation_absence_days' => '30',
            'absence_alert_days' => '8',
            'grade_a_min' => '90',
            'grade_b_min' => '80',
            'grade_c_min' => '65',
            'weekly_holidays' => ['thursday', 'friday'],
        ], $overrides);
    }

    public function test_principal_updates_school_settings_and_change_is_logged(): void
    {
        $principal = $this->userWithRole(Role::Principal);

        $this->actingAs($principal)->get('/settings/school')->assertOk()->assertSee('مشخصات مکتب');
        $this->actingAs($principal)->put('/settings/school', $this->payload())->assertRedirect('/settings/school');

        $this->assertSame('لیسه‌ی عالی آزمایشی', Setting::get('school_name'));
        $this->assertSame('50', Setting::get('pass_percent'));
        $this->assertTrue(Setting::enabled('fees_enabled'));
        $this->assertFalse(Setting::enabled('school_ledger_enabled'));
        $this->assertSame('thursday,friday', Setting::get('weekly_holidays'));
        $this->assertSame('8', Setting::get('absence_alert_days'));

        $log = ActivityLog::where('action', 'settings.updated')->latest('id')->firstOrFail();
        $this->assertSame('40', $log->old_values['pass_percent']);
        $this->assertSame('50', $log->new_values['pass_percent']);

        // نام تازه در منو و صفحه‌ی ورود
        $this->actingAs($principal)->get('/')->assertSee('لیسه‌ی عالی آزمایشی');
    }

    public function test_grading_values_are_validated(): void
    {
        $this->actingAs($this->userWithRole(Role::Principal))->put('/settings/school', $this->payload([
            'pass_percent' => '120', 'conditional_max_failed_subjects' => '-1', 'school_type' => 'x', 'weekly_holidays' => ['someday'],
            'absence_alert_days' => '0',
        ]))->assertSessionHasErrors(['pass_percent', 'conditional_max_failed_subjects', 'school_type', 'weekly_holidays.0', 'absence_alert_days']);
    }

    public function test_grade_boundaries_must_descend_above_pass_percent(): void
    {
        $principal = $this->userWithRole(Role::Principal);
        $this->actingAs($principal)->put('/settings/school', $this->payload(['grade_a_min' => '70', 'grade_b_min' => '80', 'grade_c_min' => '45']))
            ->assertSessionHasErrors(['grade_a_min', 'grade_c_min']);

        $this->actingAs($principal)->put('/settings/school', $this->payload())->assertSessionHasNoErrors();
        $this->assertSame('80', Setting::get('grade_b_min'));
    }

    public function test_logo_upload_is_public_and_replaceable(): void
    {
        Storage::fake('local');
        $principal = $this->userWithRole(Role::Principal);

        $this->actingAs($principal)->put('/settings/school', $this->payload(['logo' => UploadedFile::fake()->image('logo.png', 200, 200)]))->assertRedirect();
        $first = Setting::get('logo_path');
        Storage::disk('local')->assertExists($first);

        auth()->logout();
        $this->get('/school-logo')->assertOk();
        $this->get('/login')->assertSee(route('school.logo'), false);

        $this->actingAs($principal)->put('/settings/school', $this->payload(['remove_logo' => '1']))->assertRedirect();
        Storage::disk('local')->assertMissing($first);
        $this->get('/school-logo')->assertNotFound();
    }

    public function test_only_settings_managers_can_open(): void
    {
        foreach ([Role::Office, Role::Teacher, Role::Accountant] as $role) {
            $this->actingAs($this->userWithRole($role))->get('/settings/school')->assertForbidden();
        }
    }
}
