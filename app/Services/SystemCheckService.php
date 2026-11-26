<?php

namespace App\Services;

use App\Enums\Role as RoleKey;
use App\Models\AcademicYear;
use App\Models\Role;
use App\Models\Setting;
use App\Support\JalaliDate;
use Carbon\CarbonImmutable;
use Illuminate\Support\Facades\DB;
use Throwable;

/**
 * بررسی حالت سیستم (tools\check.bat ← php artisan school:check).
 * هر مورد: level (ok | warn | error)، label، message و fix (کلید رفع خودکار در school:check --fix یا null).
 * هیچ چیزی را تغییر نمی‌دهد؛ رفع خودکار در خود دستور است.
 */
class SystemCheckService
{
    public const EXTENSIONS = ['pdo_mysql', 'mbstring', 'intl', 'zip', 'gd', 'fileinfo', 'openssl'];

    public const WRITABLE = ['storage/app/private', 'storage/framework/cache', 'storage/framework/sessions', 'storage/framework/views', 'storage/logs', 'bootstrap/cache'];

    private array $items = [];

    public function __construct(private readonly BackupService $backups) {}

    /** @return list<array{key: string, level: string, label: string, message: string, fix: ?string}> */
    public function run(): array
    {
        $this->items = [];
        $add = $this->add(...);

        // ---- PHP و فایل‌ها ----
        $add('version', 'ok', 'v'.config('app.version'));
        $add('php', version_compare(PHP_VERSION, '8.4.0', '>=') ? 'ok' : 'error', 'PHP '.PHP_VERSION);
        $missing = array_values(array_filter(self::EXTENSIONS, fn ($e) => ! extension_loaded($e)));
        $add('extensions', $missing === [] ? 'ok' : 'error', $missing === [] ? implode('، ', self::EXTENSIONS) : __('backup.check.missing', ['list' => implode('، ', $missing)]));

        $envOk = is_file(base_path('.env')) && (string) config('app.key') !== '';
        $add('env', $envOk ? 'ok' : 'error', $envOk ? '.env · APP_KEY' : __('backup.check.env_missing'));

        $production = app()->environment('production');
        $add('mode', $production && ! config('app.debug') ? 'ok' : 'warn',
            'APP_ENV='.app()->environment().' · APP_DEBUG='.(config('app.debug') ? 'true' : 'false').($production && ! config('app.debug') ? '' : ' — '.__('backup.check.mode_hint')));

        // امنیت (مرحله‌ی ۱۱): رمزنگاری session و کاربر محدود پایگاه داده فقط در production مهم است
        $encrypted = (bool) config('session.encrypt');
        $add('session', $encrypted || ! $production ? 'ok' : 'warn', $encrypted ? __('backup.check.session_encrypted') : ($production ? __('backup.check.session_plain') : __('backup.check.dev_only')));
        $dbUser = (string) config('database.connections.'.config('database.default').'.username');
        $dbPassword = (string) config('database.connections.'.config('database.default').'.password');
        $weakDb = $dbUser === 'root' || $dbPassword === '';
        $add('db_user', $production && $weakDb ? 'warn' : 'ok',
            $dbUser.($dbPassword === '' ? ' — '.__('backup.check.db_no_password') : '').($weakDb ? ' — '.($production ? __('backup.check.db_root') : __('backup.check.dev_only')) : ''));

        $bad = array_values(array_filter(self::WRITABLE, fn ($d) => ! is_dir(base_path($d)) || ! is_writable(base_path($d))));
        $add('writable', $bad === [] ? 'ok' : 'error', $bad === [] ? 'storage · bootstrap/cache' : implode('، ', $bad), 'folders');

        $free = @disk_free_space(base_path());
        if ($free !== false) {
            $gb = round($free / 1024 ** 3, 1);
            $add('disk', $free < 200 * 1024 ** 2 ? 'error' : ($free < 1024 ** 3 ? 'warn' : 'ok'), __('backup.check.disk_free', ['gb' => $gb]));
        }

        // ---- پایگاه داده ----
        try {
            $version = (string) DB::selectOne('select version() as v')->v;
            $add('database', 'ok', 'MariaDB/MySQL '.explode('-', $version)[0].' · '.DB::connection()->getDatabaseName());
        } catch (Throwable $e) {
            $add('database', 'error', __('backup.check.db_down').' ('.mb_substr($e->getMessage(), 0, 150).')');

            return $this->tail(false);
        }

        $migrator = app('migrator');
        if (! $migrator->repositoryExists()) {
            $add('migrations', 'error', __('backup.check.no_tables'), 'migrate');

            return $this->tail(false);
        }
        $files = array_keys($migrator->getMigrationFiles(database_path('migrations')));
        $pending = array_diff($files, $migrator->getRepository()->getRan());
        $add('migrations', $pending === [] ? 'ok' : 'warn', $pending === [] ? __('backup.check.migrations_ok', ['n' => count($files)]) : __('backup.check.migrations_pending', ['n' => count($pending)]), 'migrate');

        $damaged = $this->damagedTables();
        $add('tables', $damaged === [] ? 'ok' : 'error', $damaged === [] ? __('backup.check.tables_ok') : __('backup.check.tables_bad', ['list' => implode('، ', $damaged)]));

        if ($pending === []) {
            $adminRole = Role::where('key', RoleKey::Admin->value)->first();
            $admins = $adminRole ? $adminRole->users()->where('is_active', true)->count() : 0;
            $add('admin', $admins > 0 ? 'ok' : 'error', $admins > 0 ? __('backup.check.admins', ['n' => $admins]) : __('backup.check.no_admin'), 'seed');

            $missingSettings = array_diff(array_keys((array) config('school.defaults')), DB::table('settings')->pluck('key')->all());
            $named = (string) Setting::get('school_name') !== (string) config('school.defaults.school_name');
            $add('settings', $missingSettings !== [] ? 'warn' : ($named ? 'ok' : 'warn'),
                $missingSettings !== [] ? __('backup.check.settings_missing', ['n' => count($missingSettings)]) : ($named ? Setting::get('school_name') : __('backup.check.school_unnamed')),
                $missingSettings !== [] ? 'seed' : null);

            $license = app(LicenseService::class)->status();
            $add('license', in_array($license['state'], ['not_configured', 'valid'], true) ? 'ok' : 'warn',
                $license['state'] === 'not_configured' ? __('license.not_configured') : __('license.states.'.$license['state']));

            $year = AcademicYear::current();
            $add('year', $year ? 'ok' : 'warn', $year ? __('academics.year').' '.$year->name : __('academics.no_year'));
        }

        return $this->tail(true);
    }

    /** مواردی که بدون پایگاه داده هم بررسی می‌شوند */
    private function tail(bool $dbUp): array
    {
        $add = $this->add(...);
        $latest = $this->backups->latest();
        if ($latest === null) {
            $add('backup', 'warn', __('backup.check.no_backup'), $dbUp ? 'backup' : null);
        } else {
            $days = (int) $latest['time']->diffInDays(CarbonImmutable::now(JalaliDate::TIMEZONE));
            $due = $this->backups->isDue();
            $add('backup', $due ? 'warn' : 'ok', __('backup.check.last_backup', ['date' => JalaliDate::format($latest['time'], 'long'), 'days' => $days]), $dbUp ? 'backup' : null);
        }
        $tool = $this->backups->binary('mysqldump');
        $add('mysqldump', is_file($tool) ? 'ok' : 'warn', is_file($tool) ? $tool : __('backup.check.no_mysqldump'));

        $add('web', $this->portOpen(80) ? 'ok' : 'warn', $this->portOpen(80) ? __('backup.check.web_ok') : __('backup.check.web_down'));

        $now = CarbonImmutable::now(JalaliDate::TIMEZONE);
        $add('clock', config('app.timezone') === JalaliDate::TIMEZONE ? 'ok' : 'warn', JalaliDate::format($now, 'full').' · '.$now->format('H:i').' — '.__('backup.check.clock_hint'));

        $error = $this->lastLoggedError();
        $add('log', $error === null ? 'ok' : 'warn', $error === null ? __('backup.check.log_ok') : __('backup.check.log_error', ['date' => $error]), 'caches');

        return $this->items;
    }

    private function add(string $key, string $level, string $message, ?string $fix = null): void
    {
        $this->items[] = ['key' => $key, 'level' => $level, 'label' => __('backup.check.'.$key), 'message' => $message, 'fix' => $level === 'ok' ? null : $fix];
    }

    /** @return list<string> */
    private function damagedTables(): array
    {
        // CHECK TABLE در MySQL transaction باز را خودکار commit می‌کند؛ پس روی یک اتصال جدا اجرا می‌شود
        $connection = DB::build(config('database.connections.'.config('database.default')));
        $damaged = [];
        foreach ($connection->getSchemaBuilder()->getTableListing($connection->getDatabaseName(), schemaQualified: false) as $table) {
            try {
                $rows = $connection->select('CHECK TABLE `'.str_replace('`', '', $table).'`');
                // جدول سالم: سطر آخر status با متن OK؛ هر سطر error یعنی خراب
                $types = array_map(fn ($r) => strtolower((string) $r->Msg_type), $rows);
                $last = end($rows);
                if (in_array('error', $types, true) || ($last && strtolower((string) $last->Msg_type) === 'status' && strtolower((string) $last->Msg_text) !== 'ok')) {
                    $damaged[] = $table;
                }
            } catch (Throwable) {
                $damaged[] = $table;
            }
        }

        $connection->disconnect();

        return $damaged;
    }

    private function portOpen(int $port): bool
    {
        $socket = @fsockopen('127.0.0.1', $port, $code, $message, 0.5);
        if ($socket) {
            fclose($socket);

            return true;
        }

        return false;
    }

    /** تاریخ آخرین خطای ثبت‌شده در storage/logs/laravel.log در ۷ روز گذشته (یا null) */
    private function lastLoggedError(): ?string
    {
        $file = storage_path('logs/laravel.log');
        if (! is_file($file)) {
            return null;
        }
        $size = filesize($file);
        $handle = fopen($file, 'rb');
        fseek($handle, max(0, $size - 200_000));
        $tail = (string) stream_get_contents($handle);
        fclose($handle);

        // فقط خطاهای همین محیط (تست‌ها با «testing.» در همین فایل می‌نویسند)
        $env = preg_quote((string) app()->environment(), '/');
        if (! preg_match_all('/^\[(\d{4}-\d{2}-\d{2}) [\d:]+\] '.$env.'\.(?:ERROR|CRITICAL|ALERT|EMERGENCY):/m', $tail, $m)) {
            return null;
        }
        $date = CarbonImmutable::parse(end($m[1]), JalaliDate::TIMEZONE);

        return $date->gte(CarbonImmutable::now(JalaliDate::TIMEZONE)->subDays(7)->startOfDay()) ? JalaliDate::format($date, 'long') : null;
    }
}
