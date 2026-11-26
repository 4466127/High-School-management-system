<?php

namespace App\Console\Commands;

use App\Services\LicenseService;
use Illuminate\Console\Command;

/**
 * فقط برای صاحب نرم‌افزار، یک بار: جفت کلید لایسنس.
 * کلید خصوصی در پوشه‌ای بیرون از پروژه نوشته می‌شود و هرگز در مخزن نمی‌آید؛ کلید عمومی در config/license.php.
 */
class LicenseKeygenCommand extends Command
{
    protected $signature = 'license:keygen {--out= : پوشه‌ی بیرون از پروژه برای کلید خصوصی (مثلاً فلش شخصی)}';

    protected $description = '(صاحب نرم‌افزار) ساختن کلید خصوصی و عمومی لایسنس';

    public function handle(): int
    {
        $dir = rtrim((string) $this->option('out'), '\\/');
        if ($dir === '' || ! is_dir($dir)) {
            $this->error('--out=<folder> (existing folder outside the project)');

            return self::FAILURE;
        }
        $real = (string) realpath($dir);
        if (str_starts_with(strtolower($real), strtolower((string) realpath(base_path())))) {
            $this->error('The private key must be stored outside the project folder.');

            return self::FAILURE;
        }
        $file = $real.DIRECTORY_SEPARATOR.'license-private.key';
        if (is_file($file)) {
            $this->error('Already exists (not overwritten): '.$file);

            return self::FAILURE;
        }

        $keys = LicenseService::generateKeys();
        file_put_contents($file, $keys['secret']);

        $this->info('Private key (keep secret, make a second copy): '.$file);
        $this->line('Public key — put it in config/license.php (public_key) and commit:');
        $this->line($keys['public']);

        return self::SUCCESS;
    }
}
