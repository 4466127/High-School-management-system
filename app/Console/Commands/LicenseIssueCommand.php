<?php

namespace App\Console\Commands;

use App\Services\LicenseService;
use Illuminate\Console\Command;
use Illuminate\Support\Str;

/** فقط برای صاحب نرم‌افزار: ساختن فایل لایسنس یک مکتب با کلید خصوصی. */
class LicenseIssueCommand extends Command
{
    protected $signature = 'license:issue
        {--key= : فایل کلید خصوصی (license:keygen)}
        {--school= : نام مکتب}
        {--installation= : شناسه‌ی نصب مکتب (خالی = هر نصب)}
        {--expires= : تاریخ ختم میلادی YYYY-MM-DD (خالی = دائمی)}
        {--edition= : نوع لایسنس، مثلاً standard}
        {--out= : مسیر فایل خروجی}';

    protected $description = '(صاحب نرم‌افزار) ساختن فایل لایسنس امضاشده برای یک مکتب';

    public function handle(): int
    {
        $key = (string) $this->option('key');
        $school = trim((string) $this->option('school'));
        $out = (string) $this->option('out');
        $expires = trim((string) $this->option('expires'));

        if (! is_file($key) || $school === '' || $out === '') {
            $this->error('Required: --key=<private key file> --school="<name>" --out=<license.json>');

            return self::FAILURE;
        }
        if ($expires !== '' && ! preg_match('/^\d{4}-\d{2}-\d{2}$/', $expires)) {
            $this->error('--expires must be YYYY-MM-DD');

            return self::FAILURE;
        }

        $license = [
            'license_id' => 'L-'.now()->format('Ymd').'-'.Str::upper(Str::random(6)),
            'school_name' => $school,
            'installation_id' => trim((string) $this->option('installation')),
            'edition' => trim((string) $this->option('edition')),
            'issued_on' => now()->toDateString(),
            'expires_on' => $expires,
        ];

        try {
            file_put_contents($out, LicenseService::sign($license, (string) file_get_contents($key)));
        } catch (\InvalidArgumentException $e) {
            $this->error($e->getMessage());

            return self::FAILURE;
        }

        $this->info('License written: '.$out.' ('.$license['license_id'].')');

        return self::SUCCESS;
    }
}
