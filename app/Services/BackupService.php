<?php

namespace App\Services;

use App\Models\User;
use App\Support\JalaliDate;
use Carbon\CarbonImmutable;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\Storage;
use InvalidArgumentException;
use RuntimeException;
use Symfony\Component\Process\Process;
use Throwable;
use ZipArchive;

/**
 * پشتیبان و بازیابی (مرحله‌ی ۱۰).
 *
 * هر پشتیبان یک فایل zip در پوشه‌ی school.backup.path (پیش‌فرض backups/ کنار پروژه، بیرون از git):
 *   database.sql   — خروجی کامل پایگاه داده با mysqldump
 *   files/private  — عکس‌ها، اسناد و لوگو (دیسک local)
 *   files/public   — دیسک public (اگر فایلی داشته باشد)
 *   manifest.json  — نوع، زمان، نام پایگاه داده، آخرین migration و شمارها
 * نام فایل: maktab-<سال-ماه-روز میلادی>_<ساعت>-<نوع>.zip (به ترتیب زمان مرتب می‌شود).
 * بازیابی پیش از هر جایگزینی از وضعیت فعلی پشتیبان «pre_restore» می‌گیرد.
 */
class BackupService
{
    public const REASONS = ['auto', 'manual', 'pre_restore', 'pre_reset'];

    private const FORMAT = 1;

    private const NAME_PATTERN = '/^maktab-(\d{4}-\d{2}-\d{2}_\d{6})-(auto|manual|pre_restore|pre_reset)\.zip$/';

    /** پوشه در zip => دیسک Laravel */
    private const FILE_DISKS = ['private' => 'local', 'public' => 'public'];

    public function __construct(private readonly ActivityLogger $logger) {}

    public function directory(): string
    {
        $dir = rtrim((string) config('school.backup.path'), '\\/');
        File::ensureDirectoryExists($dir);

        return $dir;
    }

    /**
     * @return array{name: string, path: string, size: int, reason: string, time: CarbonImmutable, copies: list<string>, copy_errors: list<string>}
     */
    public function create(string $reason = 'manual', ?User $user = null, ?string $copyTo = null): array
    {
        if (! in_array($reason, self::REASONS, true)) {
            throw new InvalidArgumentException("Unknown backup reason [{$reason}].");
        }

        $now = CarbonImmutable::now(JalaliDate::TIMEZONE);
        $name = 'maktab-'.$now->format('Y-m-d_His').'-'.$reason.'.zip';
        $path = $this->directory().DIRECTORY_SEPARATOR.$name;
        while (is_file($path)) {   // چند پشتیبان در یک ثانیه
            $now = $now->addSecond();
            $name = 'maktab-'.$now->format('Y-m-d_His').'-'.$reason.'.zip';
            $path = $this->directory().DIRECTORY_SEPARATOR.$name;
        }
        $sql = storage_path('framework'.DIRECTORY_SEPARATOR.'backup-'.bin2hex(random_bytes(6)).'.sql');

        try {
            $this->dumpDatabase($sql);

            $zip = new ZipArchive;
            if ($zip->open($path, ZipArchive::CREATE | ZipArchive::OVERWRITE) !== true) {
                throw new RuntimeException(__('backup.error_write', ['path' => $path]));
            }
            $zip->addFile($sql, 'database.sql');
            $files = 0;
            foreach (self::FILE_DISKS as $folder => $disk) {
                $root = Storage::disk($disk)->path('');
                if (! is_dir($root)) {
                    continue;
                }
                foreach (File::allFiles($root) as $file) {   // بدون فایل‌های پنهان (.gitignore)
                    $zip->addFile($file->getPathname(), 'files/'.$folder.'/'.str_replace('\\', '/', $file->getRelativePathname()));
                    $files++;
                }
            }
            $zip->addFromString('manifest.json', json_encode(
                $this->manifest($reason, $now, $files),
                JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES
            ));
            if (! $zip->close()) {
                throw new RuntimeException(__('backup.error_write', ['path' => $path]));
            }
        } catch (Throwable $e) {
            if (is_file($path)) {
                File::delete($path);
            }
            throw $e;
        } finally {
            if (is_file($sql)) {
                File::delete($sql);
            }
        }

        [$copies, $copyErrors] = $this->copy($path, array_filter([$copyTo, (string) config('school.backup.copy_to')]));
        $this->prune($reason);

        $size = (int) filesize($path);
        $this->logger->log('backup.created', null, $name, [], ['reason' => $reason, 'size_kb' => (int) round($size / 1024)], $user);

        return ['name' => $name, 'path' => $path, 'size' => $size, 'reason' => $reason, 'time' => $now, 'copies' => $copies, 'copy_errors' => $copyErrors];
    }

    /**
     * همه‌ی پشتیبان‌ها، تازه‌ترین اول.
     *
     * @return Collection<int, array{name: string, path: string, size: int, reason: string, time: CarbonImmutable}>
     */
    public function all(): Collection
    {
        return collect(File::files($this->directory()))
            ->map(function ($file) {
                if (! preg_match(self::NAME_PATTERN, $file->getFilename(), $m)) {
                    return null;
                }

                return [
                    'name' => $file->getFilename(),
                    'path' => $file->getPathname(),
                    'size' => (int) $file->getSize(),
                    'reason' => $m[2],
                    'time' => CarbonImmutable::createFromFormat('Y-m-d_His', $m[1], JalaliDate::TIMEZONE),
                ];
            })
            ->filter()
            ->sortByDesc('name')
            ->values();
    }

    public function latest(): ?array
    {
        return $this->all()->first();
    }

    /** آیا پشتیبان خودکار ماهانه وقتش رسیده است؟ (هیچ پشتیبان، یا آخرین کهنه‌تر از every_days) */
    public function isDue(): bool
    {
        $latest = $this->latest();
        $days = max(1, (int) config('school.backup.every_days', 30));

        return $latest === null || $latest['time']->lte(CarbonImmutable::now(JalaliDate::TIMEZONE)->subDays($days));
    }

    /** مسیر یک پشتیبان از روی نام (فقط نام‌های معتبر؛ بدون پوشه) */
    public function find(string $name): ?string
    {
        if (! preg_match(self::NAME_PATTERN, $name)) {
            return null;
        }
        $path = $this->directory().DIRECTORY_SEPARATOR.$name;

        return is_file($path) ? $path : null;
    }

    /** خواندن manifest و بررسی این‌که فایل پشتیبان همین سیستم است */
    public function inspect(string $path): array
    {
        if (! is_file($path)) {
            throw new RuntimeException(__('backup.not_found', ['file' => $path]));
        }
        $zip = new ZipArchive;
        if ($zip->open($path, ZipArchive::RDONLY) !== true) {
            throw new RuntimeException(__('backup.invalid'));
        }
        $manifest = json_decode((string) $zip->getFromName('manifest.json'), true);
        $hasSql = $zip->locateName('database.sql') !== false;
        $unsafe = false;
        for ($i = 0; $i < $zip->numFiles; $i++) {
            $entry = (string) $zip->getNameIndex($i);
            if (str_contains($entry, '..') || str_starts_with($entry, '/') || str_starts_with($entry, '\\') || str_contains($entry, ':')) {
                $unsafe = true;
            }
        }
        $zip->close();

        if (! is_array($manifest) || ($manifest['app'] ?? null) !== 'maktab' || ! $hasSql || $unsafe) {
            throw new RuntimeException(__('backup.invalid'));
        }

        return $manifest;
    }

    /**
     * بازیابی کامل: پایگاه داده و فایل‌ها با محتوای پشتیبان جایگزین می‌شوند.
     * پیش از آن از وضعیت فعلی پشتیبان pre_restore گرفته می‌شود؛ اگر نشد، هیچ چیزی عوض نمی‌شود.
     *
     * @return array{manifest: array, safety: array}
     */
    public function restore(string $path): array
    {
        $manifest = $this->inspect($path);
        $safety = $this->create('pre_restore');

        $tmp = storage_path('framework'.DIRECTORY_SEPARATOR.'restore-'.bin2hex(random_bytes(6)));
        try {
            $zip = new ZipArchive;
            if ($zip->open($path, ZipArchive::RDONLY) !== true || ! $zip->extractTo($tmp)) {
                throw new RuntimeException(__('backup.invalid'));
            }
            $zip->close();

            $this->importDatabase($tmp.DIRECTORY_SEPARATOR.'database.sql');

            foreach (self::FILE_DISKS as $folder => $disk) {
                $target = Storage::disk($disk)->path('');
                $this->clearDirectory($target);
                $source = $tmp.DIRECTORY_SEPARATOR.'files'.DIRECTORY_SEPARATOR.$folder;
                if (is_dir($source)) {
                    File::copyDirectory($source, $target);
                }
            }
        } finally {
            File::deleteDirectory($tmp);
        }

        // پشتیبان از نسخه‌ی قدیمی‌تر برنامه: جدول‌های تازه ساخته شوند (هیچ داده‌ای پاک نمی‌شود)
        Artisan::call('migrate', ['--force' => true]);
        Artisan::call('cache:clear');

        $this->logger->log('backup.restored', null, basename($path), [], ['safety' => $safety['name']], null);

        return ['manifest' => $manifest, 'safety' => $safety];
    }

    public function binary(string $tool): string
    {
        $exe = rtrim((string) config('school.backup.mysql_bin'), '\\/').DIRECTORY_SEPARATOR.$tool.(PHP_OS_FAMILY === 'Windows' ? '.exe' : '');

        return is_file($exe) ? $exe : $tool;
    }

    protected function dumpDatabase(string $file): void
    {
        $c = $this->connection();
        $this->runMysqlTool([
            $this->binary('mysqldump'),
            '--host='.$c['host'], '--port='.$c['port'], '--user='.$c['username'],
            '--single-transaction', '--routines', '--triggers', '--add-drop-table', '--hex-blob',
            '--default-character-set=utf8mb4',
            '--result-file='.$file,
            $c['database'],
        ]);

        if (! is_file($file) || filesize($file) === 0) {
            throw new RuntimeException(__('backup.error_mysql', ['tool' => 'mysqldump', 'message' => '']));
        }
    }

    protected function importDatabase(string $file): void
    {
        $c = $this->connection();
        $input = fopen($file, 'rb');
        try {
            $this->runMysqlTool([
                $this->binary('mysql'),
                '--host='.$c['host'], '--port='.$c['port'], '--user='.$c['username'],
                '--default-character-set=utf8mb4',
                $c['database'],
            ], $input);
        } finally {
            fclose($input);
        }
    }

    /** @param  resource|null  $input */
    private function runMysqlTool(array $command, $input = null): void
    {
        $password = $this->connection()['password'];
        $process = new Process($command, null, $password !== '' ? ['MYSQL_PWD' => $password] : [], $input, 1800);
        try {
            $process->run();
        } catch (Throwable $e) {
            throw new RuntimeException(__('backup.error_mysql', ['tool' => basename($command[0]), 'message' => $e->getMessage()]), 0, $e);
        }
        if (! $process->isSuccessful()) {
            $message = trim($process->getErrorOutput() ?: $process->getOutput());
            throw new RuntimeException(__('backup.error_mysql', ['tool' => basename($command[0]), 'message' => mb_substr($message, 0, 400)]));
        }
    }

    /** @return array{host: string, port: string, username: string, password: string, database: string} */
    private function connection(): array
    {
        $config = config('database.connections.'.config('database.default'));

        return [
            'host' => (string) ($config['host'] ?? '127.0.0.1'),
            'port' => (string) ($config['port'] ?? '3306'),
            'username' => (string) ($config['username'] ?? 'root'),
            'password' => (string) ($config['password'] ?? ''),
            'database' => (string) DB::connection()->getDatabaseName(),
        ];
    }

    private function manifest(string $reason, CarbonImmutable $now, int $files): array
    {
        $count = fn (string $table) => Schema::hasTable($table) ? DB::table($table)->count() : null;

        return [
            'app' => 'maktab',
            'format' => self::FORMAT,
            'reason' => $reason,
            'created_at' => $now->toIso8601String(),
            'database' => DB::connection()->getDatabaseName(),
            'migration' => Schema::hasTable('migrations') ? DB::table('migrations')->max('migration') : null,
            'counts' => [
                'students' => $count('students'),
                'staff' => $count('staff'),
                'users' => $count('users'),
                'marks' => $count('marks'),
            ],
            'files' => $files,
        ];
    }

    /** @return array{0: list<string>, 1: list<string>} */
    private function copy(string $path, array $targets): array
    {
        $copies = [];
        $errors = [];
        foreach (array_unique($targets) as $dir) {
            $dir = rtrim($dir, '\\/');
            if ($dir === '' || realpath($dir) === realpath(dirname($path))) {
                continue;
            }
            if (! is_dir($dir) && ! @mkdir($dir, 0777, true)) {
                $errors[] = __('backup.copy_failed', ['dir' => $dir]);

                continue;
            }
            if (@copy($path, $dir.DIRECTORY_SEPARATOR.basename($path))) {
                $copies[] = $dir;
            } else {
                $errors[] = __('backup.copy_failed', ['dir' => $dir]);
            }
        }

        return [$copies, $errors];
    }

    /** نگه داشتن keep نسخه‌ی تازه از هر نوع */
    private function prune(string $reason): void
    {
        $keep = max(1, (int) config('school.backup.keep', 12));
        $this->all()->where('reason', $reason)->slice($keep)->each(fn ($b) => File::delete($b['path']));
    }

    /** خالی کردن یک پوشه، به جز فایل‌های پنهان سطح اول (.gitignore) */
    private function clearDirectory(string $dir): void
    {
        File::ensureDirectoryExists($dir);
        foreach (File::directories($dir) as $sub) {
            File::deleteDirectory($sub);
        }
        foreach (File::files($dir) as $file) {
            File::delete($file->getPathname());
        }
    }
}
