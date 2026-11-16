<?php

namespace Database\Seeders;

use App\Models\ExamType;
use Illuminate\Database\Seeder;

/**
 * انواع امتحان پیش‌فرض (تصمیم ۲ ROADMAP): چهارونیم‌ماهه از ۴۰ + سالانه از ۶۰ = ۱۰۰.
 * امتحان ماهوار در نتیجه‌ی نهایی حساب نمی‌شود. فقط اگر هنوز هیچ نوعی نیست؛ همه قابل تغییر.
 */
class ExamTypeSeeder extends Seeder
{
    public function run(): void
    {
        if (ExamType::exists()) {
            return;
        }

        ExamType::create(['name' => 'امتحان چهارونیم‌ماهه', 'default_max_mark' => 40, 'default_weight' => 40, 'counts_in_final' => true, 'sort_order' => 1]);
        ExamType::create(['name' => 'امتحان سالانه', 'default_max_mark' => 60, 'default_weight' => 60, 'counts_in_final' => true, 'sort_order' => 2]);
        ExamType::create(['name' => 'امتحان ماهوار', 'default_max_mark' => 20, 'default_weight' => 0, 'counts_in_final' => false, 'sort_order' => 3]);
    }
}
