<?php

namespace App\Console\Commands;

use App\Services\ResetService;
use App\Support\Digits;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use Throwable;

/**
 * پاک کردن اطلاعات برای شروع کار واقعی مکتب (tools\reset.bat). راهنما: README بخش ابزارها.
 *   php artisan school:reset                      ← می‌پرسد چه چیزی و تأیید با نوشتن نام پایگاه داده
 *   php artisan school:reset --data|--full        ← نوع از پیش
 *   php artisan school:reset --full --confirm=maktab
 * همیشه پیش از پاک کردن پشتیبان کامل می‌گیرد؛ اگر نشد، هیچ چیزی پاک نمی‌شود.
 */
class ResetCommand extends Command
{
    protected $signature = 'school:reset
        {--data : پاک کردن اطلاعات مکتب (کاربران و تنظیمات می‌مانند)}
        {--full : پاک کردن کامل، مثل نصب تازه}
        {--confirm= : نام پایگاه داده برای تأیید بدون پرسیدن}';

    protected $description = 'پاک کردن اطلاعات آزمایشی برای شروع کار واقعی (با پشتیبان پیش از آن)';

    public function handle(ResetService $reset): int
    {
        $this->line('== '.__('backup.reset_title').' ==');
        $db = DB::connection()->getDatabaseName();
        $this->line(__('backup.reset.database', ['db' => $db]));
        $this->line(__('backup.reset.now', $reset->counts()));
        $this->newLine();

        $mode = match (true) {
            (bool) $this->option('data') => 'data',
            (bool) $this->option('full') => 'full',
            default => null,
        };
        if ($mode === null) {
            $this->line(__('backup.reset.what'));
            $this->line(__('backup.reset.what_data'));
            $this->line(__('backup.reset.what_full'));
            $this->line(__('backup.reset.what_none'));
            $mode = match (Digits::toLatin(trim((string) $this->ask(__('backup.reset.ask'), '0')))) {
                '1' => 'data',
                '2' => 'full',
                default => null,
            };
            if ($mode === null) {
                $this->line(__('backup.cli.cancelled'));

                return self::SUCCESS;
            }
        }

        $this->newLine();
        $this->warn('[!] '.__('backup.reset_mode_'.$mode).' — '.__('backup.reset.warn'));
        $typed = $this->option('confirm') ?? $this->ask(__('backup.cli.confirm', ['db' => $db]));
        if (trim((string) $typed) !== $db) {
            $this->line(__('backup.cli.cancelled'));

            return self::SUCCESS;
        }

        $this->line('[..] '.__('backup.reset.backing_up'));
        try {
            $r = $reset->run($mode);
        } catch (Throwable $e) {
            $this->error('[ERROR] '.$e->getMessage());
            $this->error('[ERROR] '.__('backup.reset.backup_failed'));

            return self::FAILURE;
        }

        $this->info('[OK] '.__('backup.reset.backup_done', ['file' => $r['backup']['path']]));
        $this->info('[OK] '.__('backup.reset.done', ['n' => $r['tables']]));
        $this->line(__('backup.reset.now', $reset->counts()));
        if ($r['admin']) {
            $this->newLine();
            $this->warn('[!!] '.__('backup.reset.admin', $r['admin']));
        }
        $this->newLine();
        $this->line(__('backup.reset.undo'));

        return self::SUCCESS;
    }
}
