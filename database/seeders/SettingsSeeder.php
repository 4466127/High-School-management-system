<?php

namespace Database\Seeders;

use App\Models\Setting;
use Illuminate\Database\Seeder;

/** مقدار پیش‌فرض تنظیمات (config/school.php). کلیدهای موجود هرگز عوض نمی‌شوند. */
class SettingsSeeder extends Seeder
{
    public function run(): void
    {
        $now = now();
        $rows = collect(config('school.defaults'))
            ->map(fn ($value, $key) => ['key' => $key, 'value' => $value, 'created_at' => $now, 'updated_at' => $now])
            ->values()
            ->all();

        Setting::query()->insertOrIgnore($rows);
        Setting::flushCache();
    }
}
