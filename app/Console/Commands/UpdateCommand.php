<?php

namespace App\Console\Commands;

use App\Services\ActivityLogger;
use App\Services\BackupService;
use App\Services\SystemCheckService;
use Database\Seeders\DatabaseSeeder;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Artisan;
use Throwable;

/**
 * به‌روزرسانی امن یک نصب، بعد از کاپی کردن فایل‌های نسخه‌ی تازه (tools\update.bat؛ DEPLOYMENT.md).
 * ترتیب: پشتیبان «پیش از به‌روزرسانی» ← حالت نگه‌داری ← migrationهای تازه ← seed پایه (فقط افزودنی) ← cache ← بررسی سلامت.
 * اگر پشتیبان نشد، هیچ چیزی عوض نمی‌شود. هیچ داده‌ای پاک نمی‌شود.
 */
class UpdateCommand extends Command
{
    protected $signature = 'school:update';

    protected $description = 'به‌روزرسانی امن: پشتیبان، migration، تنظیمات تازه، cache و بررسی سلامت';

    public function handle(BackupService $backups, SystemCheckService $checker, ActivityLogger $logger): int
    {
        $this->line('== '.__('backup.update.title').' == v'.config('app.version'));

        $pending = $this->pendingMigrations();
        try {
            $this->line('[..] '.__('backup.cli.working'));
            $backup = $backups->create('pre_update');
            $this->info('[OK] '.__('backup.cli.done', ['file' => $backup['path'], 'size' => number_format($backup['size'] / 1024, 1)]));
        } catch (Throwable $e) {
            $this->error('[XX] '.__('backup.update.no_backup', ['message' => $e->getMessage()]));

            return self::FAILURE;
        }

        Artisan::call('down', ['--retry' => 30]);
        try {
            $this->line('[..] '.__('backup.update.migrating', ['n' => count($pending)]));
            Artisan::call('migrate', ['--force' => true]);
            app(DatabaseSeeder::class)->setContainer(app())->setCommand($this)->callSilent(DatabaseSeeder::BASE);
            Artisan::call('optimize:clear');
            if (app()->environment('production')) {
                Artisan::call('optimize');
            }
        } catch (Throwable $e) {
            Artisan::call('up');
            $this->error('[XX] '.__('backup.update.failed', ['message' => $e->getMessage(), 'file' => $backup['path']]));

            return self::FAILURE;
        }
        Artisan::call('up');

        $logger->log('system.updated', null, 'v'.config('app.version'), new: ['version' => config('app.version'), 'backup' => $backup['name']]);
        $this->info('[OK] '.__('backup.update.done', ['version' => config('app.version')]));

        $errors = 0;
        foreach ($checker->run() as $item) {
            if ($item['level'] === 'error') {
                $errors++;
                $this->error('[XX]  '.$item['label'].': '.$item['message']);
            }
        }
        $this->line($errors === 0 ? __('backup.check.summary_ok') : __('backup.update.check_errors'));

        return $errors === 0 ? self::SUCCESS : self::FAILURE;
    }

    private function pendingMigrations(): array
    {
        $migrator = app('migrator');
        if (! $migrator->repositoryExists()) {
            return [];
        }

        return array_diff(array_keys($migrator->getMigrationFiles(database_path('migrations'))), $migrator->getRepository()->getRan());
    }
}
