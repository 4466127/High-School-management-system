<?php

namespace Database\Seeders;

use App\Models\ExamType;
use Illuminate\Database\Seeder;

/**
 * انواع امتحان پیش‌فرض (خواست صاحب پروژه، ۴ قوس ۱۴۰۵): مکتب فقط دو امتحان دارد —
 * چهارونیم‌ماهه ۴۰ + سالانه ۶۰ = ۱۰۰. کارخانگی و فعالیت صنفی را هر معلم داخل همین دو نمره حساب می‌کند.
 * «امتحان ماهوار» غیرفعال است (در نتیجه حساب نمی‌شود؛ مکتب در صورت نیاز روشنش می‌کند).
 * هر نوعی که با همین نام نیست ساخته می‌شود؛ نوع موجود هرگز تغییر نمی‌کند (تنظیم مکتب محفوظ می‌ماند).
 */
class ExamTypeSeeder extends Seeder
{
    public const DEFAULTS = [
        ['name' => 'امتحان چهارونیم‌ماهه', 'default_max_mark' => 40, 'default_weight' => 40, 'counts_in_final' => true, 'sort_order' => 1, 'is_active' => true],
        ['name' => 'امتحان سالانه', 'default_max_mark' => 60, 'default_weight' => 60, 'counts_in_final' => true, 'sort_order' => 2, 'is_active' => true],
        ['name' => 'امتحان ماهوار', 'default_max_mark' => 20, 'default_weight' => 20, 'counts_in_final' => false, 'sort_order' => 3, 'is_active' => false],
    ];

    public function run(): void
    {
        foreach (self::DEFAULTS as $type) {
            ExamType::firstOrCreate(['name' => $type['name']], $type);
        }
    }
}
