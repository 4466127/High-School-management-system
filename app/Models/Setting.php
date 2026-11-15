<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Cache;

/**
 * تنظیمات مکتب (کلید/مقدار). خواندن از cache؛ هر تغییر از راه put() که cache را پاک می‌کند.
 * مقدار پیش‌فرض هر کلید در config/school.php است.
 */
#[Fillable(['key', 'value'])]
class Setting extends Model
{
    public const CACHE_KEY = 'school.settings';

    protected $primaryKey = 'key';

    public $incrementing = false;

    protected $keyType = 'string';

    public static function get(string $key, mixed $default = null): mixed
    {
        $all = Cache::rememberForever(self::CACHE_KEY, fn () => static::query()->pluck('value', 'key')->all());

        return $all[$key] ?? config('school.defaults.'.$key, $default);
    }

    public static function enabled(string $key): bool
    {
        return (string) static::get($key, '0') === '1';
    }

    public static function put(string $key, mixed $value): void
    {
        static::query()->updateOrCreate(['key' => $key], ['value' => $value === null ? null : (string) $value]);
        static::flushCache();
    }

    public static function flushCache(): void
    {
        Cache::forget(self::CACHE_KEY);
    }
}
