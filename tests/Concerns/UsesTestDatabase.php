<?php

namespace Tests\Concerns;

use Database\Seeders\ExamTypeSeeder;
use Database\Seeders\GradeLevelSeeder;
use Database\Seeders\RolePermissionSeeder;
use Database\Seeders\SettingsSeeder;
use Database\Seeders\SubjectSeeder;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\DB;
use RuntimeException;

/**
 * پایگاه داده‌ی تست بدون migrate:fresh:
 *  - فقط روی پایگاه داده‌ای که نامش به «_test» ختم می‌شود اجرا می‌شود (هرگز روی maktab)؛
 *  - در هر اجرای تست‌ها یک بار «migrate» عادی + seederهای نقش و تنظیمات (هر دو اجرای دوباره را تحمل می‌کنند)؛
 *  - هر تست داخل یک transaction اجرا و در پایان برگردانده می‌شود.
 */
trait UsesTestDatabase
{
    use DatabaseTransactions {
        beginDatabaseTransaction as protected beginBaseDatabaseTransaction;
    }

    private static bool $testDatabasePrepared = false;

    public function beginDatabaseTransaction(): void
    {
        $this->prepareTestDatabaseOnce();
        $this->beginBaseDatabaseTransaction();
    }

    private function prepareTestDatabaseOnce(): void
    {
        $database = (string) DB::connection()->getDatabaseName();

        if (! str_ends_with($database, '_test')) {
            throw new RuntimeException("Refusing to run tests on database [{$database}]; its name must end with \"_test\".");
        }

        if (self::$testDatabasePrepared) {
            return;
        }

        Artisan::call('migrate', ['--force' => true]);
        $this->seed([RolePermissionSeeder::class, SettingsSeeder::class, GradeLevelSeeder::class, SubjectSeeder::class, ExamTypeSeeder::class]);

        self::$testDatabasePrepared = true;
    }
}
