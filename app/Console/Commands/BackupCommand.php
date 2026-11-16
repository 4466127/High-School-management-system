<?php

namespace App\Console\Commands;

use App\Services\BackupService;
use App\Support\JalaliDate;
use Carbon\CarbonImmutable;
use Illuminate\Console\Command;
use Throwable;

/** پشتیبان از خط فرمان (tools\backup.bat؛ دکمه‌ی دسکتاپ با --if-due ماهانه) */
class BackupCommand extends Command
{
    protected $signature = 'school:backup
        {--to= : یک کاپی هم در این پوشه (مثلاً فلش E:\maktab-backup)}
        {--if-due : فقط اگر آخرین پشتیبان کهنه‌تر از school.backup.every_days باشد (پشتیبان خودکار ماهانه)}';

    protected $description = 'پشتیبان کامل: پایگاه داده + عکس‌ها و اسناد در یک فایل zip';

    public function handle(BackupService $backups): int
    {
        $this->line('== '.__('backup.cli.backup_title').' ==');

        if ($this->option('if-due') && ! $backups->isDue()) {
            $days = (int) $backups->latest()['time']->diffInDays(CarbonImmutable::now(JalaliDate::TIMEZONE));
            $this->info('[OK] '.__('backup.cli.not_due', ['days' => $days]));

            return self::SUCCESS;
        }

        $this->line('[..] '.__('backup.cli.working'));
        try {
            $r = $backups->create($this->option('if-due') ? 'auto' : 'manual', null, $this->option('to') ?: null);
        } catch (Throwable $e) {
            $this->error('[ERROR] '.$e->getMessage());

            return self::FAILURE;
        }

        $this->info('[OK] '.__('backup.cli.done', ['file' => $r['path'], 'size' => number_format($r['size'] / 1024, 1)]));
        foreach ($r['copies'] as $dir) {
            $this->info('[OK] '.__('backup.cli.copied', ['dir' => $dir]));
        }
        foreach ($r['copy_errors'] as $error) {
            $this->warn('[WARN] '.$error);
        }

        return self::SUCCESS;
    }
}
