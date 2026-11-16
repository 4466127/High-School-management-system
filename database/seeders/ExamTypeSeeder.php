<?php

namespace Database\Seeders;

use App\Models\ExamType;
use Illuminate\Database\Seeder;

/**
 * انواع امتحان پیش‌فرض (خواست صاحب پروژه، ۲۵ عقرب ۱۴۰۵):
 * فعالیت صنفی و کارخانگی ۲۰ + چهارونیم‌ماهه ۳۰ + سالانه ۵۰ = ۱۰۰. امتحان ماهوار در نتیجه حساب نمی‌شود.
 * هر نوعی که با همین نام نیست ساخته می‌شود؛ نوع موجود هرگز تغییر نمی‌کند (تنظیم مکتب محفوظ می‌ماند).
 */
class ExamTypeSeeder extends Seeder
{
    public const DEFAULTS = [
        ['name' => 'فعالیت صنفی و کارخانگی', 'default_max_mark' => 20, 'default_weight' => 20, 'counts_in_final' => true, 'sort_order' => 1],
        ['name' => 'امتحان چهارونیم‌ماهه', 'default_max_mark' => 30, 'default_weight' => 30, 'counts_in_final' => true, 'sort_order' => 2],
        ['name' => 'امتحان سالانه', 'default_max_mark' => 50, 'default_weight' => 50, 'counts_in_final' => true, 'sort_order' => 3],
        ['name' => 'امتحان ماهوار', 'default_max_mark' => 20, 'default_weight' => 0, 'counts_in_final' => false, 'sort_order' => 4],
    ];

    public function run(): void
    {
        foreach (self::DEFAULTS as $type) {
            ExamType::firstOrCreate(['name' => $type['name']], $type + ['is_active' => true]);
        }
    }
}
