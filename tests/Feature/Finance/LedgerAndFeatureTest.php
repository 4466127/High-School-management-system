<?php

namespace Tests\Feature\Finance;

use App\Enums\Role;
use App\Models\ActivityLog;
use App\Models\FinanceCategory;
use App\Models\SchoolTransaction;
use App\Models\Setting;
use App\Support\Menu;

/** درآمد و مصرف، و خاموش/روشن بودن بخش‌های مالی. */
class LedgerAndFeatureTest extends FinanceTestCase
{
    public function test_income_and_expense_with_totals_and_void(): void
    {
        $income = FinanceCategory::create(['type' => 'income', 'name' => 'کمک تست']);
        $expense = FinanceCategory::create(['type' => 'expense', 'name' => 'قرطاسیه تست']);

        $this->actingAs($this->accountant)->post(route('finance.ledger.store'), [
            'type' => 'income', 'finance_category_id' => $income->id, 'amount' => '10,000', 'date' => '1405/08/20', 'party_name' => 'حاجی محمد',
        ])->assertSessionHas('success');
        $this->actingAs($this->accountant)->post(route('finance.ledger.store'), [
            'type' => 'expense', 'finance_category_id' => $expense->id, 'amount' => '2500', 'date' => '1405/08/21',
        ])->assertSessionHas('success');

        // دسته‌ی مصرف برای درآمد پذیرفته نمی‌شود؛ تاریخ آینده هم نه
        $this->actingAs($this->accountant)->post(route('finance.ledger.store'), [
            'type' => 'income', 'finance_category_id' => $expense->id, 'amount' => '100', 'date' => '1405/09/30',
        ])->assertSessionHasErrors(['finance_category_id', 'date']);

        $this->issue('میزان ۱۴۰۵');
        $this->pay($this->students[0]->student, '1000');

        $this->actingAs($this->accountant)->get(route('finance.ledger.index'))
            ->assertOk()->assertSee('حاجی محمد')->assertSee('۱۰٬۰۰۰ افغانی')->assertSee('۸٬۵۰۰ افغانی');   // ۱۰۰۰۰ + ۱۰۰۰ − ۲۵۰۰

        $tx = SchoolTransaction::where('type', 'expense')->sole();
        $this->actingAs($this->accountant)->patch(route('finance.ledger.void', $tx), ['reason' => 'دوبار ثبت شده'])->assertSessionHas('success');
        $this->actingAs($this->accountant)->get(route('finance.ledger.index'))->assertSee('۱۱٬۰۰۰ افغانی');
        $this->assertTrue(ActivityLog::where('action', 'transaction.voided')->exists());

        $csv = $this->actingAs($this->accountant)->get(route('finance.ledger.export'))->assertOk()->streamedContent();
        $this->assertStringContainsString('10000', $csv);
    }

    public function test_finance_is_closed_when_switched_off(): void
    {
        Setting::put('fees_enabled', '0');
        $this->actingAs($this->accountant)->get(route('finance.payments.index'))->assertNotFound();
        $this->actingAs($this->accountant)->get(route('finance.invoices.index'))->assertNotFound();
        $this->actingAs($this->accountant)->get(route('finance.ledger.index'))->assertOk();

        $menu = collect(app(Menu::class)->for($this->accountant))->firstWhere('key', 'finance');
        $this->assertSame(['finance_ledger'], array_column($menu['children'], 'key'));
        $this->actingAs($this->accountant)->get(route('students.show', $this->students[0]->student))->assertOk()->assertDontSee(__('finance.issue_single'));

        Setting::put('school_ledger_enabled', '0');
        $this->actingAs($this->accountant)->get(route('finance.ledger.index'))->assertNotFound();
        $this->assertNull(collect(app(Menu::class)->for($this->accountant))->firstWhere('key', 'finance'));
    }

    public function test_menu_when_everything_is_on(): void
    {
        $menu = collect(app(Menu::class)->for($this->userWithRole(Role::Principal)))->firstWhere('key', 'finance');
        $this->assertSame(['finance_payments', 'finance_invoices', 'finance_debtors', 'finance_ledger', 'finance_setup'], array_column($menu['children'], 'key'));
    }
}
