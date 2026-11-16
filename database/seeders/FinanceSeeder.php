<?php

namespace Database\Seeders;

use App\Models\FeeType;
use App\Models\FinanceCategory;
use Illuminate\Database\Seeder;

/**
 * پیش‌فرض‌های مالی (همه‌ی محیط‌ها؛ فقط اگر جدول خالی باشد): دو نوع فیس و دسته‌های رایج درآمد و مصرف.
 * مبلغ‌ها (نرخ) را مکتب خودش تعیین می‌کند.
 */
class FinanceSeeder extends Seeder
{
    public function run(): void
    {
        if (! FeeType::exists()) {
            FeeType::create(['name' => 'فیس ماهوار', 'period' => 'monthly', 'sort_order' => 1]);
            FeeType::create(['name' => 'فیس ثبت‌نام', 'period' => 'once', 'sort_order' => 2]);
        }

        if (! FinanceCategory::exists()) {
            foreach (['کمک مردمی', 'کمک موسسه‌ها', 'درآمد جانبی'] as $name) {
                FinanceCategory::create(['type' => 'income', 'name' => $name]);
            }
            foreach (['قرطاسیه', 'برق و آب', 'ترمیم و نگهداری', 'پاک‌کاری', 'سوخت زمستانی', 'متفرقه'] as $name) {
                FinanceCategory::create(['type' => 'expense', 'name' => $name]);
            }
        }
    }
}
