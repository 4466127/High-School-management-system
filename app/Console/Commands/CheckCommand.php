<?php

namespace App\Console\Commands;

use App\Services\BackupService;
use App\Services\ResetService;
use App\Services\SystemCheckService;
use Database\Seeders\DatabaseSeeder;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\File;
use Throwable;

/**
 * بررسی حالت سیستم (tools\check.bat). با --fix مشکل‌های قابل رفع را خودکار رفع می‌کند:
 * ساختن پوشه‌های لازم، اجرای migrationهای تازه (پس از پشتیبان)، seed نقش‌ها و تنظیمات و مدیر سیستم،
 * پاک و ساختن دوباره‌ی cache، و گرفتن پشتیبان اگر کهنه است. هیچ اطلاعاتی پاک نمی‌شود.
 * کد خروج: ۰ همه سالم؛ ۱ خطا یا مورد قابل رفع.
 */
class CheckCommand extends Command
{
    protected $signature = 'school:check {--fix : رفع خودکار مشکل‌های قابل رفع}';

    protected $description = 'بررسی حالت سیستم: PHP، پایگاه داده، جدول‌ها، پوشه‌ها، پشتیبان، Apache';

    private const ICONS = ['ok' => '[OK]  ', 'warn' => '[!!]  ', 'error' => '[XX]  '];

    public function handle(SystemCheckService $checker, BackupService $backups, ResetService $reset): int
    {
        $this->line('== '.__('backup.check_title').' ==');
        $items = $this->report($checker);

        if ($this->option('fix')) {
            $fixes = array_unique(array_filter(array_column($items, 'fix')));
            $fixes[] = 'caches';
            $this->newLine();
            $this->line('[..] '.__('backup.check.fixing'));
            foreach (['folders', 'migrate', 'seed', 'caches', 'backup'] as $fix) {
                if (in_array($fix, $fixes, true)) {
                    $this->fix($fix, $backups, $reset);
                }
            }
            $this->newLine();
            $items = $this->report($checker);
        }

        $errors = count(array_filter($items, fn ($i) => $i['level'] === 'error'));
        $warnings = count(array_filter($items, fn ($i) => $i['level'] === 'warn'));
        $fixable = array_filter($items, fn ($i) => $i['fix'] !== null);

        $this->newLine();
        if ($errors === 0 && $warnings === 0) {
            $this->info(__('backup.check.summary_ok'));
        } else {
            $this->line(__('backup.check.summary', ['errors' => $errors, 'warnings' => $warnings]));
            if ($fixable !== [] && ! $this->option('fix')) {
                $this->line(__('backup.check.fixable'));
            }
        }

        return $errors > 0 || ($fixable !== [] && ! $this->option('fix')) ? self::FAILURE : self::SUCCESS;
    }

    private function report(SystemCheckService $checker): array
    {
        $items = $checker->run();
        foreach ($items as $item) {
            $line = self::ICONS[$item['level']].$item['label'].': '.$item['message'];
            match ($item['level']) {
                'ok' => $this->line($line),
                'warn' => $this->warn($line),
                default => $this->error($line),
            };
        }

        return $items;
    }

    private function fix(string $fix, BackupService $backups, ResetService $reset): void
    {
        try {
            switch ($fix) {
                case 'folders':
                    foreach (SystemCheckService::WRITABLE as $dir) {
                        File::ensureDirectoryExists(base_path($dir));
                    }
                    $this->info('[OK] '.__('backup.check.fix_folders'));
                    break;

                case 'migrate':
                    if (app('migrator')->repositoryExists() && count(app('migrator')->getRepository()->getRan()) > 0) {
                        $b = $backups->create('manual');
                        $this->info('[OK] '.__('backup.check.fix_backup', ['file' => $b['path']]));
                    }
                    Artisan::call('migrate', ['--force' => true]);
                    $this->info('[OK] '.__('backup.check.fix_migrate'));
                    break;

                case 'seed':
                    app(DatabaseSeeder::class)->setContainer(app())->callSilent(DatabaseSeeder::BASE);
                    $admin = $reset->ensureAdmin();
                    $this->info('[OK] '.__('backup.check.fix_seed'));
                    if ($admin) {
                        $this->warn('[!!] '.__('backup.check.fix_admin', $admin));
                    }
                    break;

                case 'caches':
                    Artisan::call('optimize:clear');
                    if (app()->environment('production')) {
                        Artisan::call('optimize');
                    }
                    $this->info('[OK] '.__('backup.check.fix_caches'));
                    break;

                case 'backup':
                    if ($backups->isDue()) {
                        $b = $backups->create('manual');
                        $this->info('[OK] '.__('backup.check.fix_backup_new', ['file' => $b['path']]));
                    }
                    break;
            }
        } catch (Throwable $e) {
            $this->error('[XX] '.__('backup.check.fix_failed', ['message' => $e->getMessage()]));
        }
    }
}
