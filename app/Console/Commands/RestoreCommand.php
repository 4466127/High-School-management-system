<?php

namespace App\Console\Commands;

use App\Services\BackupService;
use App\Support\Clock;
use App\Support\Digits;
use App\Support\JalaliDate;
use Carbon\CarbonImmutable;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use Throwable;

/**
 * بازیابی از پشتیبان (tools\restore.bat). فقط روی خود سرور؛ صفحه‌ی وب بازیابی ندارد.
 * بدون مسیر: فهرست پشتیبان‌های پوشه‌ی backups را نشان می‌دهد تا شماره انتخاب شود.
 * تأیید با نوشتن نام پایگاه داده (یا --confirm=<نام>).
 */
class RestoreCommand extends Command
{
    protected $signature = 'school:restore
        {file? : مسیر فایل zip پشتیبان (خالی = انتخاب از فهرست)}
        {--confirm= : نام پایگاه داده برای تأیید بدون پرسیدن}';

    protected $description = 'بازیابی پایگاه داده و فایل‌ها از یک پشتیبان (پیش از آن از وضعیت فعلی پشتیبان گرفته می‌شود)';

    public function handle(BackupService $backups): int
    {
        $this->line('== '.__('backup.cli.restore_title').' ==');
        $db = DB::connection()->getDatabaseName();

        $path = $this->argument('file');
        if (! $path) {
            $list = $backups->all()->values();
            if ($list->isEmpty()) {
                $this->warn(__('backup.cli.no_backups', ['dir' => $backups->directory()]));

                return self::FAILURE;
            }
            foreach ($list as $i => $b) {
                $this->line(sprintf('  %d) %s  ·  %s  ·  %s KB', $i + 1, JalaliDate::format($b['time'], 'long').' '.Clock::withPeriod($b['time']), __('backup.reasons.'.$b['reason']), number_format($b['size'] / 1024)));
            }
            $n = (int) Digits::toLatin((string) $this->ask(__('backup.cli.choose'), '0'));
            if ($n < 1 || $n > $list->count()) {
                $this->line(__('backup.cli.cancelled'));

                return self::SUCCESS;
            }
            $path = $list[$n - 1]['path'];
        }

        try {
            $m = $backups->inspect($path);
        } catch (Throwable $e) {
            $this->error('[ERROR] '.$e->getMessage());

            return self::FAILURE;
        }

        $created = isset($m['created_at']) ? CarbonImmutable::parse($m['created_at'])->setTimezone(JalaliDate::TIMEZONE) : null;
        $this->line(basename($path));
        $this->line(__('backup.cli.info', [
            'date' => $created ? JalaliDate::format($created, 'long').' '.Clock::withPeriod($created) : '—',
            'reason' => __('backup.reasons.'.($m['reason'] ?? 'manual')),
            'students' => $m['counts']['students'] ?? '—',
            'users' => $m['counts']['users'] ?? '—',
            'files' => $m['files'] ?? 0,
        ]));
        $this->newLine();
        $this->warn('[!] '.__('backup.cli.warn_restore', ['db' => $db]));

        $typed = $this->option('confirm') ?? $this->ask(__('backup.cli.confirm', ['db' => $db]));
        if (trim((string) $typed) !== $db) {
            $this->line(__('backup.cli.cancelled'));

            return self::SUCCESS;
        }

        $this->line('[..] '.__('backup.cli.restoring'));
        try {
            $r = $backups->restore($path);
        } catch (Throwable $e) {
            $this->error('[ERROR] '.__('backup.cli.failed', ['message' => $e->getMessage()]));
            $safety = $backups->all()->firstWhere('reason', 'pre_restore');
            if ($safety && $safety['time']->gte(CarbonImmutable::now(JalaliDate::TIMEZONE)->subMinutes(30))) {
                $this->warn(__('backup.cli.safety_hint', ['file' => $safety['path']]));
            }

            return self::FAILURE;
        }

        $this->info('[OK] '.__('backup.cli.restored', ['file' => $r['safety']['path']]));
        $this->line(__('backup.cli.login'));

        return self::SUCCESS;
    }
}
