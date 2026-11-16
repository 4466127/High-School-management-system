<?php

namespace Tests\Feature\System;

use App\Services\ActivityLogger;
use App\Services\BackupService;
use Illuminate\Filesystem\Filesystem;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\Storage;
use Tests\Feature\Exams\ExamTestCase;

/**
 * زمینه‌ی تست‌های مرحله‌ی ۱۰: پوشه‌ی پشتیبان موقت، دیسک‌های فایل جعلی (فایل‌های واقعی دست نمی‌خورد).
 * mysqldump واقعی روی maktab_test اجرا می‌شود (فقط خواندن)؛ وارد کردن SQL جعلی است، چون تست داخل transaction است.
 */
abstract class SystemTestCase extends ExamTestCase
{
    protected string $backupDir;

    protected function setUp(): void
    {
        parent::setUp();
        $this->backupDir = self::backupRoot().DIRECTORY_SEPARATOR.bin2hex(random_bytes(4));
        config(['school.backup.path' => $this->backupDir, 'school.backup.copy_to' => '', 'school.backup.keep' => 12]);
        Storage::fake('local');
        Storage::fake('public');
    }

    protected function tearDown(): void
    {
        File::deleteDirectory($this->backupDir);
        parent::tearDown();
    }

    /** پوشه‌ای که Windows گاهی خالی نگه می‌دارد، در پایان همه‌ی تست‌های کلاس پاک می‌شود */
    public static function tearDownAfterClass(): void
    {
        (new Filesystem)->deleteDirectory(self::backupRoot());
        parent::tearDownAfterClass();
    }

    private static function backupRoot(): string
    {
        return dirname(__DIR__, 3).DIRECTORY_SEPARATOR.'storage'.DIRECTORY_SEPARATOR.'framework'.DIRECTORY_SEPARATOR.'testing'.DIRECTORY_SEPARATOR.'backups';
    }

    /** BackupService با importDatabase جعلی (SQL خوانده و نگه داشته می‌شود، اجرا نمی‌شود) */
    protected function fakeImport(): BackupService
    {
        $service = new class(app(ActivityLogger::class)) extends BackupService
        {
            public array $imported = [];

            protected function importDatabase(string $file): void
            {
                $this->imported[] = file_get_contents($file);
            }
        };
        $this->app->instance(BackupService::class, $service);

        return $service;
    }

    /** یک فایل پشتیبان ساختگی با نام و زمان دلخواه (برای فهرست و نگهداری) */
    protected function dummyBackup(string $stamp, string $reason = 'manual'): string
    {
        File::ensureDirectoryExists($this->backupDir);
        $path = $this->backupDir.DIRECTORY_SEPARATOR."maktab-{$stamp}-{$reason}.zip";
        file_put_contents($path, 'x');

        return $path;
    }
}
