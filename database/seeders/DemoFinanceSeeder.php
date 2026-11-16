<?php

namespace Database\Seeders;

use App\Models\AcademicYear;
use App\Models\FeeRate;
use App\Models\FeeType;
use App\Models\FinanceCategory;
use App\Models\GradeLevel;
use App\Models\Payment;
use App\Models\Section;
use App\Models\Student;
use App\Models\StudentInvoice;
use App\Models\User;
use App\Services\FinanceService;
use App\Support\Money;
use Illuminate\Database\Seeder;
use RuntimeException;

/**
 * فقط برای توسعه: نرخ فیس ماهوار هر صنف، بل‌های میزان و عقرب ۱۴۰۵، چند رسید و چند درآمد/مصرف.
 * داده پنهان می‌ماند تا «مدیریت فیس» و «دفتر درآمد و مصرف» در مشخصات مکتب روشن شود. اگر بلی هست، کاری نمی‌کند.
 */
class DemoFinanceSeeder extends Seeder
{
    public function run(FinanceService $finance): void
    {
        if (app()->isProduction()) {
            throw new RuntimeException('DemoFinanceSeeder must not run in production.');
        }
        $year = AcademicYear::current();
        $monthly = FeeType::where('period', 'monthly')->first();
        $admin = User::where('username', 'admin')->first();
        if (! $year || ! $monthly || ! $admin || StudentInvoice::exists()) {
            $this->command?->info('Invoices already exist (or no year/fee type); demo finance skipped.');

            return;
        }

        mt_srand(7);
        foreach (GradeLevel::orderBy('sort_order')->get() as $grade) {
            FeeRate::firstOrCreate(
                ['fee_type_id' => $monthly->id, 'academic_year_id' => $year->id, 'grade_level_id' => $grade->id],
                ['amount' => 500 + 100 * intdiv($grade->sort_order - 1, 3)],
            );
        }

        $sections = Section::where('academic_year_id', $year->id)->get();
        foreach (FinanceService::periodOptions($monthly, $year) as $i => $period) {
            if (in_array($i, [6, 7], true)) {   // میزان و عقرب
                $finance->issueGroup($monthly, $period, $sections, null, $admin);
            }
        }

        foreach (Student::whereHas('currentEnrollment')->orderBy('id')->get() as $n => $student) {
            $due = $finance->invoicesFor($student, onlyOpen: true)->sum('remaining_cents');
            $pay = match ($n % 3) {
                0 => $due,
                1 => intdiv($due, 2),
                default => 0,
            };
            if ($pay > 0) {
                $finance->pay($student, $pay, now()->subDays(mt_rand(1, 20))->toDateString(), null, null, null, $admin);
            }
        }

        $income = FinanceCategory::where('type', 'income')->first();
        $expense = FinanceCategory::where('type', 'expense')->get();
        if ($income) {
            $finance->recordTransaction(['type' => 'income', 'finance_category_id' => $income->id, 'amount' => '25000.00', 'date' => now()->subDays(25)->toDateString(), 'party_name' => 'شورای والدین', 'description' => 'کمک برای ترمیم صنف‌ها'], $admin);
        }
        foreach ($expense->take(3) as $i => $category) {
            $finance->recordTransaction(['type' => 'expense', 'finance_category_id' => $category->id, 'amount' => Money::fromCents((3000 + 1500 * $i) * 100), 'date' => now()->subDays(10 + $i)->toDateString(), 'description' => $category->name], $admin);
        }

        $this->command?->info('Demo finance: '.StudentInvoice::count().' invoices, '.Payment::count().' receipts.');
    }
}
