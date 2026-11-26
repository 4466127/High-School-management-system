<?php

namespace App\Console\Commands;

use App\Services\LicenseService;
use App\Services\SetupService;
use Database\Seeders\DatabaseSeeder;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Artisan;

/**
 * نصب برای یک مکتب تازه (DEPLOYMENT.md). اجرای دوباره امن است: فقط جدول‌ها و تنظیمات تازه را اضافه می‌کند.
 * اگر هنوز مدیر سیستمی نیست، یک کد یک‌بار مصرف می‌دهد تا مشخصات مکتب و اولین مدیر در صفحه‌ی /setup ثبت شود.
 * داده‌ی آزمایشی (demo) هرگز ساخته نمی‌شود.
 */
class InstallCommand extends Command
{
    protected $signature = 'school:install {--new-token : کد راه‌اندازی تازه (کد قبلی باطل می‌شود)}';

    protected $description = 'نصب برای مکتب تازه: جدول‌ها، ساختار پایه و کد راه‌اندازی صفحه‌ی /setup';

    public function handle(SetupService $setup, LicenseService $license): int
    {
        $this->line('== '.__('setup.cli.title').' == v'.config('app.version'));

        if ((string) config('app.key') === '') {
            $this->error('[XX] '.__('setup.cli.no_key'));

            return self::FAILURE;
        }

        $this->line('[..] '.__('setup.cli.migrating'));
        Artisan::call('migrate', ['--force' => true]);
        $this->line('[..] '.__('setup.cli.seeding'));
        app(DatabaseSeeder::class)->setContainer(app())->setCommand($this)->callSilent(DatabaseSeeder::BASE);

        $this->info('[OK] '.__('setup.cli.installation_id', ['id' => $license->installationId()]));

        if ($setup->hasAdmin()) {
            $this->info('[OK] '.__('setup.cli.installed'));

            return self::SUCCESS;
        }

        if (! $setup->pending() || $this->option('new-token')) {
            $token = $setup->issueToken();
            $this->newLine();
            $this->warn('    '.__('setup.cli.token', ['token' => $token, 'hours' => SetupService::TOKEN_HOURS]));
        } else {
            $this->line('[..] '.__('setup.cli.token_exists'));
        }
        $this->line('    '.__('setup.cli.open', ['url' => rtrim((string) config('app.url'), '/').'/setup']));

        return self::SUCCESS;
    }
}
