<?php

namespace App\Services;

use App\Models\Setting;
use App\Models\User;
use Carbon\CarbonImmutable;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;
use InvalidArgumentException;
use JsonException;

/**
 * لایسنس آفلاین: فایل JSON با محتوا (payload) و امضای Ed25519 آن.
 * فقط وضعیت را می‌گوید؛ هیچ بخش برنامه را نمی‌بندد (ROADMAP بخش ۸.۲).
 *
 * قالب فایل: {"format": 1, "payload": base64(JSON), "signature": base64}
 * محتوا: license_id، school_name، installation_id (خالی = هر نصب)، edition، issued_on، expires_on (خالی = دائمی)، notes
 */
class LicenseService
{
    public const FORMAT = 1;

    public const STATES = ['not_configured', 'missing', 'invalid', 'other_installation', 'expired', 'expiring', 'valid'];

    public function __construct(private readonly ActivityLogger $logger) {}

    /** شناسه‌ی این نصب؛ اولین بار ساخته و در تنظیمات نگه داشته می‌شود */
    public function installationId(): string
    {
        $id = (string) Setting::get('installation_id');
        if ($id === '') {
            $id = (string) Str::uuid();
            Setting::put('installation_id', $id);
        }

        return $id;
    }

    public function configured(): bool
    {
        return $this->publicKey() !== null;
    }

    /** @return array{state: string, license: ?array, days_left: ?int} */
    public function status(): array
    {
        if (! $this->configured()) {
            return ['state' => 'not_configured', 'license' => null, 'days_left' => null];
        }
        $path = (string) config('license.path');
        if (! is_file($path)) {
            return ['state' => 'missing', 'license' => null, 'days_left' => null];
        }

        try {
            $license = $this->decode((string) file_get_contents($path));
        } catch (ValidationException) {
            return ['state' => 'invalid', 'license' => null, 'days_left' => null];
        }

        return $this->evaluate($license);
    }

    /** آیا مدیر سیستم باید هشدار ببیند؟ (تنظیم نشده و معتبر ← نه) */
    public function needsAttention(): bool
    {
        return ! in_array($this->status()['state'], ['not_configured', 'valid'], true);
    }

    /** وضعیت یک لایسنس بازشده (امضا قبلاً بررسی شده) */
    public function evaluate(array $license): array
    {
        if (($license['installation_id'] ?? '') !== '' && $license['installation_id'] !== $this->installationId()) {
            return ['state' => 'other_installation', 'license' => $license, 'days_left' => null];
        }
        if (($license['expires_on'] ?? '') === '') {
            return ['state' => 'valid', 'license' => $license, 'days_left' => null];
        }

        $tz = config('app.timezone');
        $days = (int) CarbonImmutable::now($tz)->startOfDay()->diffInDays(CarbonImmutable::parse($license['expires_on'], $tz)->startOfDay(), false);
        $state = $days < 0 ? 'expired' : ($days <= (int) config('license.warn_days') ? 'expiring' : 'valid');

        return ['state' => $state, 'license' => $license, 'days_left' => $days];
    }

    /** نصب فایل لایسنس (از صفحه‌ی تنظیمات یا خط فرمان). فایل نامعتبر یا مال نصب دیگر ذخیره نمی‌شود. */
    public function install(string $contents, ?User $user = null): array
    {
        if (! $this->configured()) {
            throw ValidationException::withMessages(['license' => __('license.not_configured')]);
        }
        $license = $this->decode($contents);
        $status = $this->evaluate($license);
        if ($status['state'] === 'other_installation') {
            throw ValidationException::withMessages(['license' => __('license.states.other_installation')]);
        }

        $path = (string) config('license.path');
        if (! is_dir(dirname($path))) {
            mkdir(dirname($path), 0755, true);
        }
        file_put_contents($path, trim($contents)."\n", LOCK_EX);

        $this->logger->log('system.license_installed', null, $license['license_id'], new: [
            'school' => $license['school_name'], 'edition' => (string) ($license['edition'] ?? ''), 'expires_on' => (string) ($license['expires_on'] ?? ''),
        ], user: $user);

        return $status;
    }

    /**
     * بازکردن و بررسی امضا.
     *
     * @throws ValidationException
     */
    public function decode(string $contents): array
    {
        $fail = fn () => ValidationException::withMessages(['license' => __('license.states.invalid')]);
        $key = $this->publicKey();

        try {
            $file = json_decode(trim($contents), true, 4, JSON_THROW_ON_ERROR);
        } catch (JsonException) {
            throw $fail();
        }
        if (! is_array($file) || ($file['format'] ?? null) !== self::FORMAT || ! is_string($file['payload'] ?? null) || ! is_string($file['signature'] ?? null)) {
            throw $fail();
        }

        $payload = base64_decode($file['payload'], true);
        $signature = base64_decode($file['signature'], true);
        if ($key === null || $payload === false || $signature === false || strlen($signature) !== SODIUM_CRYPTO_SIGN_BYTES
            || ! sodium_crypto_sign_verify_detached($signature, $payload, $key)) {
            throw $fail();
        }

        try {
            $license = json_decode($payload, true, 4, JSON_THROW_ON_ERROR);
        } catch (JsonException) {
            throw $fail();
        }
        if (! is_array($license) || ! is_string($license['license_id'] ?? null) || ! is_string($license['school_name'] ?? null)) {
            throw $fail();
        }
        foreach (['issued_on', 'expires_on'] as $date) {
            if (($license[$date] ?? '') !== '' && ! preg_match('/^\d{4}-\d{2}-\d{2}$/', (string) $license[$date])) {
                throw $fail();
            }
        }

        return $license;
    }

    // ---------- ابزار صاحب نرم‌افزار (license:keygen / license:issue) ----------

    /** @return array{secret: string, public: string} هر دو base64 */
    public static function generateKeys(): array
    {
        $pair = sodium_crypto_sign_keypair();

        return [
            'secret' => base64_encode(sodium_crypto_sign_secretkey($pair)),
            'public' => base64_encode(sodium_crypto_sign_publickey($pair)),
        ];
    }

    /** ساختن متن فایل لایسنس با کلید خصوصی (base64) */
    public static function sign(array $license, string $secretKey): string
    {
        $secret = base64_decode(trim($secretKey), true);
        if ($secret === false || strlen($secret) !== SODIUM_CRYPTO_SIGN_SECRETKEYBYTES) {
            throw new InvalidArgumentException('Invalid secret key.');
        }
        $payload = json_encode($license, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_THROW_ON_ERROR);

        return json_encode([
            'format' => self::FORMAT,
            'payload' => base64_encode($payload),
            'signature' => base64_encode(sodium_crypto_sign_detached($payload, $secret)),
        ], JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES | JSON_THROW_ON_ERROR);
    }

    private function publicKey(): ?string
    {
        $key = base64_decode(trim((string) config('license.public_key')), true);

        return $key !== false && strlen($key) === SODIUM_CRYPTO_SIGN_PUBLICKEYBYTES ? $key : null;
    }
}
