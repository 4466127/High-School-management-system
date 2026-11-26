<?php

namespace App\Services;

use App\Enums\Role as RoleKey;
use App\Models\Role;
use App\Models\Setting;
use App\Models\User;
use Carbon\CarbonImmutable;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;

/**
 * راه‌اندازی نصب تازه برای یک مکتب (مرحله‌ی ۱۱).
 * «php artisan school:install» یک کد یک‌بار مصرف می‌دهد؛ صفحه‌ی /setup با همان کد مشخصات مکتب
 * و اولین مدیر سیستم را می‌گیرد. تا وقتی یک مدیر سیستم هست، صفحه‌ی راه‌اندازی وجود ندارد (۴۰۴).
 */
class SetupService
{
    public const TOKEN_KEY = 'setup_token';

    public const EXPIRES_KEY = 'setup_expires_at';

    public const TOKEN_HOURS = 48;

    /** فیلدهای مشخصات مکتب که در راه‌اندازی گرفته می‌شوند */
    public const SCHOOL_KEYS = ['school_name', 'school_type', 'province', 'district', 'address', 'phone', 'principal_name'];

    public function __construct(
        private readonly ActivityLogger $logger,
        private readonly LicenseService $license,
    ) {}

    public function hasAdmin(): bool
    {
        $role = Role::where('key', RoleKey::Admin->value)->first();

        return $role !== null && $role->users()->exists();
    }

    /** راه‌اندازی منتظر است: کد صادر شده، کهنه نشده و هنوز مدیر سیستمی نیست */
    public function pending(): bool
    {
        $expires = (string) Setting::get(self::EXPIRES_KEY);

        return (string) Setting::get(self::TOKEN_KEY) !== ''
            && $expires !== ''
            && CarbonImmutable::parse($expires)->isFuture()
            && ! $this->hasAdmin();
    }

    /** کد تازه (کد قبلی باطل می‌شود). فقط هش آن ذخیره می‌شود. */
    public function issueToken(): string
    {
        $token = Str::upper(Str::random(4).'-'.Str::random(4));
        Setting::put(self::TOKEN_KEY, Hash::make($token));
        Setting::put(self::EXPIRES_KEY, CarbonImmutable::now()->addHours(self::TOKEN_HOURS)->toIso8601String());

        return $token;
    }

    public function checkToken(string $token): bool
    {
        $hash = (string) Setting::get(self::TOKEN_KEY);

        return $hash !== '' && Hash::check(Str::upper(trim($token)), $hash);
    }

    /**
     * @param  array<string, mixed>  $data  مشخصات مکتب + name، username، password
     */
    public function complete(array $data): User
    {
        if (! $this->pending()) {
            abort(404);
        }
        if (! $this->checkToken((string) ($data['token'] ?? ''))) {
            throw ValidationException::withMessages(['token' => __('setup.token_invalid')]);
        }

        return DB::transaction(function () use ($data) {
            foreach (self::SCHOOL_KEYS as $key) {
                Setting::put($key, (string) ($data[$key] ?? ''));
            }
            if (($data['school_type'] ?? '') === 'private') {
                Setting::put('fees_enabled', '1');
            }
            Setting::put(self::TOKEN_KEY, '');
            Setting::put(self::EXPIRES_KEY, '');
            $this->license->installationId();

            $user = User::create([
                'name' => $data['name'],
                'username' => $data['username'],
                'password' => $data['password'],
                'is_active' => true,
                'must_change_password' => false,
            ]);
            $user->roles()->attach(Role::where('key', RoleKey::Admin->value)->firstOrFail());

            $this->logger->log('system.setup_completed', $user, $data['school_name'], new: ['school' => $data['school_name']], user: $user);

            return $user;
        });
    }
}
