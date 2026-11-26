<?php

namespace App\Console\Commands;

use App\Services\LicenseService;
use Illuminate\Console\Command;
use Illuminate\Validation\ValidationException;

/** وضعیت لایسنس این نصب؛ با --install=<فایل> فایل لایسنس را نصب می‌کند. */
class LicenseCommand extends Command
{
    protected $signature = 'school:license {--install= : مسیر فایل license.json برای نصب}';

    protected $description = 'وضعیت لایسنس آفلاین این نصب و نصب فایل لایسنس';

    public function handle(LicenseService $license): int
    {
        if ($file = $this->option('install')) {
            if (! is_file($file)) {
                $this->error('[XX] '.$file);

                return self::FAILURE;
            }
            try {
                $license->install((string) file_get_contents($file));
            } catch (ValidationException $e) {
                $this->error('[XX] '.collect($e->errors())->flatten()->first());

                return self::FAILURE;
            }
            $this->info('[OK] '.__('license.installed'));
        }

        $status = $license->status();
        $this->line(__('license.installation_id').': '.$license->installationId());
        $this->line(__('license.state').': '.($status['state'] === 'not_configured' ? __('license.not_configured') : __('license.states.'.$status['state'])));
        if ($l = $status['license']) {
            $this->line(__('license.license_id').': '.$l['license_id'].' · '.__('license.school').': '.$l['school_name']
                .' · '.__('license.expires_on').': '.(($l['expires_on'] ?? '') ?: __('license.permanent')));
        }

        return self::SUCCESS;
    }
}
