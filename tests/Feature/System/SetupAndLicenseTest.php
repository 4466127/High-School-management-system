<?php

namespace Tests\Feature\System;

use App\Enums\Role;
use App\Models\ActivityLog;
use App\Models\Role as RoleModel;
use App\Models\Setting;
use App\Models\User;
use App\Services\BackupService;
use App\Services\LicenseService;
use App\Services\SetupService;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\File;

/** مرحله‌ی ۱۱: راه‌اندازی نصب تازه، لایسنس آفلاین، به‌روزرسانی امن */
class SetupAndLicenseTest extends SystemTestCase
{
    private string $licensePath;

    private string $secret;

    protected function setUp(): void
    {
        parent::setUp();
        $keys = LicenseService::generateKeys();
        $this->secret = $keys['secret'];
        $this->licensePath = $this->backupDir.DIRECTORY_SEPARATOR.'license.json';
        File::ensureDirectoryExists($this->backupDir);
        config(['license.public_key' => $keys['public'], 'license.path' => $this->licensePath]);
    }

    private function licenseFile(array $overrides = [], ?string $secret = null): string
    {
        return LicenseService::sign(array_merge([
            'license_id' => 'L-TEST-1',
            'school_name' => 'لیسه‌ی آزمایشی',
            'installation_id' => app(LicenseService::class)->installationId(),
            'edition' => 'standard',
            'issued_on' => '2026-11-01',
            'expires_on' => '',
        ], $overrides), $secret ?? $this->secret);
    }

    private function removeAdmins(): void
    {
        RoleModel::where('key', Role::Admin->value)->first()->users()->detach();
    }

    // ---------- راه‌اندازی ----------

    public function test_setup_page_does_not_exist_without_token_or_when_admin_exists(): void
    {
        $this->get('/setup')->assertNotFound();
        $this->post('/setup', [])->assertNotFound();

        // کد هست ولی مدیر سیستم هم هست ← باز هم نیست
        $this->userWithRole(Role::Admin);
        app(SetupService::class)->issueToken();
        $this->get('/setup')->assertNotFound();
        $this->get('/login')->assertOk();
    }

    public function test_install_command_and_setup_create_first_admin(): void
    {
        $this->removeAdmins();
        $this->artisan('school:install')->expectsOutputToContain('/setup')->assertExitCode(0);
        $this->assertTrue(app(SetupService::class)->pending());
        $this->assertNotSame('', (string) Setting::get('installation_id'));

        // کد تازه برای تست (کد واقعی فقط در خروجی دستور است)
        $token = app(SetupService::class)->issueToken();
        $this->get('/login')->assertRedirect('/setup');
        $this->get('/setup')->assertOk()->assertSee(__('setup.title'));

        $payload = [
            'token' => 'WRONG-0000',
            'school_name' => 'لیسه‌ی نو',
            'school_type' => 'private',
            'province' => 'بلخ',
            'name' => 'مدیر نو',
            'username' => 'Principal.Admin',
            'password' => 'secret-pass-1',
            'password_confirmation' => 'secret-pass-1',
        ];
        $this->post('/setup', $payload)->assertSessionHasErrors('token');
        $this->assertGuest();

        $this->post('/setup', ['token' => strtolower($token)] + $payload)->assertRedirect('/settings/school');

        $user = User::where('username', 'principal.admin')->firstOrFail();
        $this->assertAuthenticatedAs($user);
        $this->assertTrue($user->hasRole(Role::Admin));
        $this->assertFalse((bool) $user->must_change_password);
        $this->assertSame('لیسه‌ی نو', Setting::get('school_name'));
        $this->assertTrue(Setting::enabled('fees_enabled'));
        $this->assertSame('', (string) Setting::get(SetupService::TOKEN_KEY));
        $this->assertTrue(ActivityLog::where('action', 'system.setup_completed')->exists());

        // بعد از راه‌اندازی صفحه دیگر نیست؛ اجرای دوباره‌ی install چیزی را عوض نمی‌کند
        auth()->logout();
        $this->get('/setup')->assertNotFound();
        $this->artisan('school:install')->expectsOutputToContain(__('setup.cli.installed'))->assertExitCode(0);
        $this->assertSame('لیسه‌ی نو', Setting::get('school_name'));
    }

    public function test_expired_setup_token_is_not_accepted(): void
    {
        $this->removeAdmins();
        app(SetupService::class)->issueToken();
        Setting::put(SetupService::EXPIRES_KEY, now()->subMinute()->toIso8601String());

        $this->get('/setup')->assertNotFound();
        $this->get('/login')->assertOk();
    }

    // ---------- لایسنس ----------

    public function test_license_not_configured_shows_nothing_and_blocks_nothing(): void
    {
        config(['license.public_key' => '']);
        $admin = $this->userWithRole(Role::Admin);

        $this->assertSame('not_configured', app(LicenseService::class)->status()['state']);
        $this->actingAs($admin)->get('/')->assertOk()->assertDontSee(__('license.title').'</a>', false);
        $this->actingAs($admin)->get('/settings/license')->assertOk()->assertSee(__('license.not_configured'));
    }

    public function test_admin_installs_valid_license(): void
    {
        $admin = $this->userWithRole(Role::Admin);
        $this->assertSame('missing', app(LicenseService::class)->status()['state']);
        $this->actingAs($admin)->get('/')->assertSee(__('license.states.missing'));

        $file = UploadedFile::fake()->createWithContent('license.json', $this->licenseFile());
        $this->actingAs($admin)->post('/settings/license', ['license' => $file])->assertRedirect()->assertSessionHasNoErrors();

        $status = app(LicenseService::class)->status();
        $this->assertSame('valid', $status['state']);
        $this->assertSame('L-TEST-1', $status['license']['license_id']);
        $this->assertTrue(ActivityLog::where('action', 'system.license_installed')->exists());
        $this->actingAs($admin)->get('/')->assertDontSee(__('license.states.missing'));
        $this->actingAs($admin)->get('/settings/license')->assertSee('L-TEST-1');
    }

    public function test_tampered_or_foreign_license_is_rejected(): void
    {
        $admin = $this->userWithRole(Role::Admin);

        // امضای کلید دیگر
        $other = LicenseService::generateKeys()['secret'];
        $file = UploadedFile::fake()->createWithContent('license.json', $this->licenseFile([], $other));
        $this->actingAs($admin)->post('/settings/license', ['license' => $file])->assertSessionHasErrors('license');

        // محتوای دست‌کاری‌شده
        $json = json_decode($this->licenseFile(), true);
        $payload = json_decode(base64_decode($json['payload']), true);
        $payload['expires_on'] = '2099-01-01';
        $json['payload'] = base64_encode(json_encode($payload));
        $file = UploadedFile::fake()->createWithContent('license.json', json_encode($json));
        $this->actingAs($admin)->post('/settings/license', ['license' => $file])->assertSessionHasErrors('license');

        // نصب دیگر
        $file = UploadedFile::fake()->createWithContent('license.json', $this->licenseFile(['installation_id' => 'another-install']));
        $this->actingAs($admin)->post('/settings/license', ['license' => $file])->assertSessionHasErrors('license');

        $this->assertFileDoesNotExist($this->licensePath);
    }

    public function test_expired_license_only_warns(): void
    {
        file_put_contents($this->licensePath, $this->licenseFile(['expires_on' => now()->subDay()->toDateString()]));
        $this->assertSame('expired', app(LicenseService::class)->status()['state']);

        file_put_contents($this->licensePath, $this->licenseFile(['expires_on' => now()->addDays(10)->toDateString()]));
        $this->assertSame('expiring', app(LicenseService::class)->status()['state']);

        file_put_contents($this->licensePath, $this->licenseFile(['expires_on' => now()->subDay()->toDateString()]));
        $admin = $this->userWithRole(Role::Admin);
        $this->actingAs($admin)->get('/')->assertOk()->assertSee(__('license.states.expired'));
        // هیچ چیزی بسته نمی‌شود
        $this->actingAs($admin)->get('/students')->assertOk();
        $this->actingAs($admin)->get('/settings/backups')->assertOk();
        $principal = $this->userWithRole(Role::Principal);
        $this->actingAs($principal)->get('/')->assertOk()->assertDontSee(__('license.states.expired'));
    }

    public function test_only_admin_manages_license(): void
    {
        $this->actingAs($this->userWithRole(Role::Principal))->get('/settings/license')->assertForbidden();
        $this->actingAs($this->userWithRole(Role::Office))->post('/settings/license')->assertForbidden();
    }

    public function test_license_command_installs_file(): void
    {
        $source = $this->backupDir.DIRECTORY_SEPARATOR.'incoming.json';
        file_put_contents($source, $this->licenseFile());

        $this->artisan('school:license', ['--install' => $source])->expectsOutputToContain(__('license.states.valid'))->assertExitCode(0);
        $this->assertFileExists($this->licensePath);
    }

    // ---------- به‌روزرسانی ----------

    public function test_update_takes_backup_first_and_logs_version(): void
    {
        $this->userWithRole(Role::Admin);
        $this->artisan('school:update')->expectsOutputToContain(__('backup.update.done', ['version' => config('app.version')]))->assertExitCode(0);

        $this->assertSame('pre_update', app(BackupService::class)->latest()['reason']);
        $this->assertTrue(ActivityLog::where('action', 'system.updated')->exists());
        $this->assertFalse(app()->isDownForMaintenance());
    }
}
