<?php

namespace Database\Seeders;

use App\Models\Subject;
use Illuminate\Database\Seeder;

/**
 * فهرست آغازین مضامین رایج — فقط اگر هنوز هیچ مضمونی نیست.
 * مضامین هر صنف را مکتب خودش از «صنف‌ها و مضامین» تعیین می‌کند.
 */
class SubjectSeeder extends Seeder
{
    public const NAMES = [
        'قرآن کریم', 'تعلیمات اسلامی', 'دری', 'پشتو', 'انگلیسی', 'عربی', 'ریاضی', 'ساینس',
        'فزیک', 'کیمیا', 'بیولوژی', 'جیولوجی', 'تاریخ', 'جغرافیه', 'تعلیمات مدنی',
        'کمپیوتر', 'خط و رسم', 'تربیت بدنی', 'مهارت‌های زندگی',
    ];

    public function run(): void
    {
        if (Subject::exists()) {
            return;
        }

        foreach (self::NAMES as $name) {
            Subject::create(['name' => $name, 'is_active' => true]);
        }
    }
}
