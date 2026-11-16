<?php

namespace App\Services;

use App\Enums\Role as RoleKey;
use App\Models\Role;
use App\Models\Setting;
use App\Models\User;
use Database\Seeders\DatabaseSeeder;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use InvalidArgumentException;

/**
 * پاک کردن اطلاعات برای شروع کار واقعی مکتب (tools\reset.bat ← php artisan school:reset).
 *
 *   data — اطلاعات مکتب پاک می‌شود (دانش‌آموزان، کارکنان، سال‌ها و بخش‌ها، حاضری، نمرات، مالی، کورس‌ها،
 *          ثبت رویدادها، عکس‌ها و اسناد). کاربران (به جز demo.*)، نقش‌ها، تنظیمات، صنف‌ها، مضامین،
 *          انواع امتحان، انواع فیس و دسته‌های مالی می‌مانند.
 *   full — همه‌چیز مثل نصب تازه؛ ساختار پایه دوباره seed می‌شود و یک حساب admin با رمز تازه ساخته می‌شود.
 *
 * همیشه پیش از پاک کردن پشتیبان کامل (pre_reset) گرفته می‌شود؛ اگر پشتیبان نشد، هیچ چیزی پاک نمی‌شود.
 * فهرست «می‌ماند» ثابت است؛ هر جدول تازه‌ای که بعداً اضافه شود، خودکار جزو «پاک می‌شود» است.
 */
class ResetService
{
    public const MODES = ['data', 'full'];

    private const KEEP = [
        'data' => [
            'migrations', 'cache', 'cache_locks', 'sessions',
            'users', 'roles', 'permissions', 'permission_role', 'permission_user', 'role_user',
            'settings', 'grade_levels', 'subjects', 'grade_level_subject', 'exam_types', 'fee_types', 'finance_categories',
        ],
        'full' => ['migrations', 'cache', 'cache_locks'],
    ];

    public function __construct(
        private readonly BackupService $backups,
        private readonly ActivityLogger $logger,
    ) {}

    /** شمار رکوردهای مهم (برای نشان دادن پیش و پس از پاک کردن) */
    public function counts(): array
    {
        $count = fn (string $table) => Schema::hasTable($table) ? DB::table($table)->count() : 0;

        return [
            'students' => $count('students'),
            'staff' => $count('staff'),
            'years' => $count('academic_years'),
            'marks' => $count('marks'),
            'payments' => $count('payments'),
            'courses' => $count('courses'),
            'users' => $count('users'),
        ];
    }

    /** @return list<string> */
    public function tablesToClear(string $mode): array
    {
        if (! in_array($mode, self::MODES, true)) {
            throw new InvalidArgumentException("Unknown reset mode [{$mode}].");
        }
        $database = DB::connection()->getDatabaseName();
        $tables = collect(Schema::getTables($database))
            ->filter(fn ($t) => ($t['schema'] ?? $database) === $database)
            ->pluck('name')
            ->all();

        return array_values(array_diff($tables, self::KEEP[$mode]));
    }

    /**
     * @return array{backup: ?array, tables: int, admin: ?array{username: string, password: string}}
     */
    public function run(string $mode, bool $backup = true): array
    {
        $tables = $this->tablesToClear($mode);
        $backupInfo = $backup ? $this->backups->create('pre_reset') : null;   // خطا ← هیچ چیزی پاک نمی‌شود

        // TRUNCATE شماره‌ها را از ۱ شروع می‌کند ولی transaction را می‌بندد؛ داخل transaction (تست‌ها) DELETE
        $truncate = DB::transactionLevel() === 0;
        $logo = $mode === 'data' ? (string) Setting::get('logo_path') : '';

        Schema::disableForeignKeyConstraints();
        try {
            foreach ($tables as $table) {
                $truncate ? DB::table($table)->truncate() : DB::table($table)->delete();
            }
            if ($mode === 'data') {
                DB::table('users')->update(['staff_id' => null]);
                DB::table('users')->where('username', 'like', 'demo.%')->delete();
                DB::table('role_user')->whereNotIn('user_id', DB::table('users')->select('id'))->delete();
                DB::table('permission_user')->whereNotIn('user_id', DB::table('users')->select('id'))->delete();
                DB::table('sessions')->whereNotNull('user_id')->whereNotIn('user_id', DB::table('users')->select('id'))->delete();
            }
        } finally {
            Schema::enableForeignKeyConstraints();
        }

        Artisan::call('cache:clear');
        Setting::flushCache();
        if ($mode === 'full') {
            app(DatabaseSeeder::class)->setContainer(app())->callSilent(DatabaseSeeder::BASE);
            Setting::flushCache();
        }
        $admin = $this->ensureAdmin();
        $this->clearFiles($logo);

        $this->logger->log('system.reset', null, __('backup.reset_mode_'.$mode), [], ['mode' => $mode, 'tables' => count($tables), 'backup' => $backupInfo['name'] ?? null], null);

        return ['backup' => $backupInfo, 'tables' => count($tables), 'admin' => $admin];
    }

    /** اگر هیچ مدیر سیستم فعالی نماند، حساب admin با رمز تازه (در اولین ورود باید عوض شود) */
    public function ensureAdmin(): ?array
    {
        $role = Role::where('key', RoleKey::Admin->value)->firstOrFail();
        if ($role->users()->where('is_active', true)->exists()) {
            return null;
        }

        $username = User::where('username', 'admin')->exists() ? 'admin'.random_int(100, 999) : 'admin';
        $password = Str::password(10, symbols: false);
        $user = User::create([
            'name' => __('roles.admin'),
            'username' => $username,
            'password' => $password,
            'is_active' => true,
            'must_change_password' => true,
        ]);
        $user->roles()->attach($role);

        return ['username' => $username, 'password' => $password];
    }

    /** عکس‌ها، اسناد و فایل‌های ورود Excel پاک می‌شوند؛ لوگوی مکتب (در حالت data) می‌ماند */
    private function clearFiles(string $keep): void
    {
        $disk = Storage::disk('local');
        foreach ($disk->allFiles() as $file) {
            if ($file !== $keep && ! str_starts_with(basename($file), '.')) {
                $disk->delete($file);
            }
        }
        foreach ($disk->directories() as $dir) {
            if ($disk->allFiles($dir) === []) {
                $disk->deleteDirectory($dir);
            }
        }
    }
}
