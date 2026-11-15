<?php

namespace Database\Seeders;

use App\Models\GradeLevel;
use Illuminate\Database\Seeder;

/** صنف اول تا دوازدهم — فقط اگر هنوز هیچ صنفی نیست (مکتب می‌تواند صنف‌های دیگر اضافه کند). */
class GradeLevelSeeder extends Seeder
{
    public const NAMES = ['اول', 'دوم', 'سوم', 'چهارم', 'پنجم', 'ششم', 'هفتم', 'هشتم', 'نهم', 'دهم', 'یازدهم', 'دوازدهم'];

    public function run(): void
    {
        if (GradeLevel::exists()) {
            return;
        }

        foreach (self::NAMES as $i => $name) {
            GradeLevel::create(['name' => 'صنف '.$name, 'sort_order' => $i + 1, 'is_active' => true]);
        }
    }
}
