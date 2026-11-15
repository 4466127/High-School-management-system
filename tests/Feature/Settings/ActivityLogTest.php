<?php

namespace Tests\Feature\Settings;

use App\Enums\Role;
use App\Models\ActivityLog;
use App\Services\ActivityLogger;
use LogicException;
use Tests\TestCase;

class ActivityLogTest extends TestCase
{
    public function test_logs_are_append_only(): void
    {
        $log = app(ActivityLogger::class)->log('auth.login', description: 'x');

        $this->expectException(LogicException::class);
        $log->update(['description' => 'changed']);
    }

    public function test_logs_cannot_be_deleted(): void
    {
        $log = app(ActivityLogger::class)->log('auth.login');

        $this->expectException(LogicException::class);
        $log->delete();
    }

    public function test_logger_never_stores_passwords(): void
    {
        $log = app(ActivityLogger::class)->log('user.updated', old: ['password' => 'a', 'name' => 'x'], new: ['password_confirmation' => 'b']);

        $this->assertSame(['name' => 'x'], $log->old_values);
        $this->assertNull($log->new_values);
    }

    public function test_admin_views_logs_with_persian_labels_and_jalali_filter(): void
    {
        $admin = $this->userWithRole(Role::Admin);
        $this->actingAs($admin);
        app(ActivityLogger::class)->log('user.password_reset', $admin, 'مورد آزمایشی');

        $this->get('/settings/activity-logs')
            ->assertOk()
            ->assertSee('نو کردن رمز کاربر')
            ->assertSee('مورد آزمایشی');

        $this->get('/settings/activity-logs?from=۱۴۰۰/۰۱/۰۱&to=۱۴۰۰/۰۱/۰۲')
            ->assertOk()
            ->assertDontSee('مورد آزمایشی');

        $this->assertGreaterThan(0, ActivityLog::count());
    }
}
