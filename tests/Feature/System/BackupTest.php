<?php

namespace Tests\Feature\System;

use App\Enums\Role;
use App\Models\ActivityLog;
use App\Services\BackupService;
use Carbon\CarbonImmutable;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\Storage;
use RuntimeException;
use ZipArchive;

class BackupTest extends SystemTestCase
{
    public function test_backup_contains_database_files_and_manifest(): void
    {
        Storage::disk('local')->put('students/photos/a.jpg', 'photo');

        $b = app(BackupService::class)->create('manual');

        $this->assertFileExists($b['path']);
        $this->assertMatchesRegularExpression('/^maktab-2026-11-16_\d{6}-manual\.zip$/', $b['name']);
        $zip = new ZipArchive;
        $zip->open($b['path']);
        $this->assertStringContainsString('CREATE TABLE `students`', (string) $zip->getFromName('database.sql'));
        $this->assertSame('photo', $zip->getFromName('files/private/students/photos/a.jpg'));
        $manifest = json_decode((string) $zip->getFromName('manifest.json'), true);
        $zip->close();
        $this->assertSame('maktab', $manifest['app']);
        $this->assertSame('maktab_test', $manifest['database']);
        $this->assertSame(1, $manifest['files']);
        $this->assertTrue(ActivityLog::where('action', 'backup.created')->exists());
    }

    public function test_due_check_and_retention(): void
    {
        $service = app(BackupService::class);
        $this->assertTrue($service->isDue());

        $this->dummyBackup('2026-09-01_100000', 'auto');
        $this->assertTrue($service->isDue());                       // ۷۶ روز پیش
        $this->dummyBackup('2026-11-01_100000', 'auto');
        $this->assertFalse($service->isDue());                      // ۱۵ روز پیش
        $this->assertSame('maktab-2026-11-01_100000-auto.zip', $service->latest()['name']);

        config(['school.backup.keep' => 2]);
        $this->dummyBackup('2026-01-01_100000');
        $this->dummyBackup('2026-02-01_100000');
        $service->create('manual');
        $manual = $service->all()->where('reason', 'manual')->pluck('name')->all();
        $this->assertCount(2, $manual);
        $this->assertNotContains('maktab-2026-01-01_100000-manual.zip', $manual);
        $this->assertCount(2, $service->all()->where('reason', 'auto'));   // نوع دیگر دست نمی‌خورد
    }

    public function test_restore_replaces_database_and_files_after_safety_backup(): void
    {
        $service = $this->fakeImport();
        Storage::disk('local')->put('students/photos/old.jpg', 'old');
        $backup = $service->create('manual');

        Storage::disk('local')->delete('students/photos/old.jpg');
        Storage::disk('local')->put('students/photos/new.jpg', 'new');

        $r = $service->restore($backup['path']);

        $this->assertStringContainsString('CREATE TABLE `students`', $service->imported[0]);
        $this->assertTrue(Storage::disk('local')->exists('students/photos/old.jpg'));
        $this->assertFalse(Storage::disk('local')->exists('students/photos/new.jpg'));
        $this->assertSame('pre_restore', $r['safety']['reason']);
        $this->assertFileExists($r['safety']['path']);
        $this->assertTrue(ActivityLog::where('action', 'backup.restored')->exists());
    }

    public function test_foreign_or_broken_files_are_rejected(): void
    {
        $service = $this->fakeImport();
        File::ensureDirectoryExists($this->backupDir);
        $path = $this->backupDir.'/other.zip';
        $zip = new ZipArchive;
        $zip->open($path, ZipArchive::CREATE);
        $zip->addFromString('database.sql', 'select 1;');
        $zip->close();

        try {
            $service->restore($path);
            $this->fail('restore accepted a zip without manifest');
        } catch (RuntimeException $e) {
            $this->assertSame(__('backup.invalid'), $e->getMessage());
        }
        $this->assertSame([], $service->imported);
        $this->assertTrue($service->all()->isEmpty());   // پشتیبان امنیتی هم گرفته نشد
        $this->assertNull($service->find('../.env'));
    }

    public function test_restore_command_asks_for_confirmation(): void
    {
        $service = $this->fakeImport();
        $backup = $service->create('manual');

        $this->artisan('school:restore', ['file' => $backup['path'], '--confirm' => 'maktab'])
            ->expectsOutputToContain(__('backup.cli.cancelled'))
            ->assertExitCode(0);
        $this->assertSame([], $service->imported);

        $this->artisan('school:restore')
            ->expectsQuestion(__('backup.cli.choose'), '۱')
            ->expectsQuestion(__('backup.cli.confirm', ['db' => 'maktab_test']), 'maktab_test')
            ->expectsOutputToContain(__('backup.cli.restoring'))
            ->assertExitCode(0);
        $this->assertCount(1, $service->imported);
    }

    public function test_backup_command_if_due(): void
    {
        $this->artisan('school:backup', ['--if-due' => true])->expectsOutputToContain('[OK]')->assertExitCode(0);
        $this->assertSame('auto', app(BackupService::class)->latest()['reason']);

        $this->artisan('school:backup', ['--if-due' => true])
            ->expectsOutputToContain(__('backup.cli.not_due', ['days' => 0]))
            ->assertExitCode(0);
        $this->assertCount(1, app(BackupService::class)->all());
    }

    public function test_backup_page_download_and_access(): void
    {
        $admin = $this->userWithRole(Role::Admin);

        $this->actingAs($admin)->get('/')->assertSee(__('backup.dashboard_none'));
        $this->actingAs($this->principal)->get('/')->assertDontSee(__('backup.dashboard_none'));

        $this->actingAs($admin)->get(route('settings.backups.index'))->assertOk()->assertSee(__('backup.empty'));
        $this->actingAs($admin)->post(route('settings.backups.store'))->assertRedirect()->assertSessionHas('success');

        $name = app(BackupService::class)->latest()['name'];
        $this->actingAs($admin)->get(route('settings.backups.index'))->assertSee($name)->assertSee(__('backup.reasons.manual'));
        $this->actingAs($admin)->get(route('settings.backups.download', $name))->assertOk()->assertDownload($name);
        $this->actingAs($admin)->get(route('settings.backups.download', 'maktab-2026-11-16_000000-manual.zip'))->assertNotFound();
        $this->actingAs($admin)->get('/')->assertDontSee(__('backup.dashboard_none'));

        $this->actingAs($this->principal)->get(route('settings.backups.index'))->assertForbidden();
        $this->actingAs($this->principal)->get(route('settings.backups.download', $name))->assertForbidden();
    }

    public function test_old_backup_shows_dashboard_reminder(): void
    {
        $this->dummyBackup(CarbonImmutable::now('Asia/Kabul')->subDays(40)->format('Y-m-d_His'), 'auto');
        $this->actingAs($this->userWithRole(Role::Admin))->get('/')
            ->assertSee(__('backup.dashboard_due', ['days' => '۴۰']));
    }
}
