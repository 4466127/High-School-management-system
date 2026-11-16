<?php

namespace Tests\Feature\Finance;

use App\Enums\Role;
use App\Models\ActivityLog;
use App\Models\FeeRate;
use App\Models\FeeType;
use App\Models\Payment;
use App\Models\PaymentAllocation;
use App\Models\StudentInvoice;
use App\Services\FinanceService;
use App\Support\Money;
use Illuminate\Database\UniqueConstraintViolationException;

/** بل، تخفیف، پرداخت جزئی، رسید پشت‌سرهم، باطل کردن (پذیرش مرحله‌ی ۷). */
class InvoicePaymentTest extends FinanceTestCase
{
    public function test_group_issue_uses_grade_rate_and_never_duplicates(): void
    {
        $this->issue('میزان ۱۴۰۵')->assertSessionHas('success');

        $this->assertSame(3, StudentInvoice::count());
        $this->assertSame('1000.00', StudentInvoice::first()->amount);
        $log = ActivityLog::where('action', 'invoice.issued_group')->sole();
        $this->assertSame(['created' => 3, 'skipped' => 0, 'no_rate' => 1], $log->new_values);

        $this->issue('میزان ۱۴۰۵')->assertSessionHas('warning');
        $this->assertSame(3, StudentInvoice::count());

        $this->expectException(UniqueConstraintViolationException::class);
        StudentInvoice::create([
            'enrollment_id' => $this->students[0]->id, 'student_id' => $this->students[0]->student_id, 'fee_type_id' => $this->monthly->id,
            'period_label' => 'میزان ۱۴۰۵', 'amount' => 1000,
        ]);
    }

    public function test_period_must_belong_to_the_year(): void
    {
        $this->issue('حمل ۱۳۹۰')->assertStatus(422);
        $this->assertSame(0, StudentInvoice::count());
    }

    public function test_partial_payments_allocate_to_oldest_invoices_and_receipts_are_sequential(): void
    {
        $this->issue('میزان ۱۴۰۵');
        $this->issue('عقرب ۱۴۰۵');
        $student = $this->students[0]->student;

        $this->pay($student, '1,500')->assertRedirect();
        $first = Payment::sole();
        $this->assertSame(1, $first->receipt_no);
        $this->assertSame(['1000.00', '500.00'], $first->allocations()->orderBy('id')->pluck('amount')->all());
        // جمع تخصیص‌ها = مبلغ رسید
        $this->assertSame(Money::toCents($first->amount), Money::toCents(PaymentAllocation::where('payment_id', $first->id)->sum('amount')));

        $this->pay($student, '۵۰۰')->assertRedirect();
        $this->assertSame([1, 2], Payment::orderBy('receipt_no')->pluck('receipt_no')->all());
        $this->assertSame(0, app(FinanceService::class)->invoicesFor($student, onlyOpen: true)->count());

        $this->actingAs($this->accountant)->get(route('finance.payments.show', $first))
            ->assertOk()->assertSee(__('finance.receipt_title'))->assertSee('۱٬۵۰۰ افغانی')->assertSee('میزان ۱۴۰۵');
        $this->assertTrue(ActivityLog::where('action', 'payment.received')->count() === 2);
    }

    public function test_overpayment_and_wrong_allocations_are_rejected(): void
    {
        $this->issue('میزان ۱۴۰۵');
        $this->issue('عقرب ۱۴۰۵');
        $student = $this->students[0]->student;
        [$a, $b] = StudentInvoice::where('student_id', $student->id)->orderBy('id')->get()->all();

        $this->pay($student, '2001')->assertSessionHas('error', __('finance.amount_over_due', ['due' => '۲٬۰۰۰ افغانی']));
        $this->pay($student, '800', [$a->id => '500', $b->id => '200'])->assertSessionHas('error', __('finance.allocation_sum', ['amount' => '۸۰۰ افغانی']));
        $this->pay($student, '1200', [$a->id => '1200'])->assertSessionHas('error', __('finance.allocation_invalid'));
        $this->pay($student, '0')->assertSessionHasErrors('amount');
        $this->assertSame(0, Payment::count());

        // تخصیص دستی به بل دوم
        $this->pay($student, '700', [$b->id => '700'])->assertRedirect();
        $this->assertSame('700.00', PaymentAllocation::sole()->amount);
        $this->assertSame($b->id, PaymentAllocation::sole()->student_invoice_id);
    }

    public function test_voided_receipt_restores_balance_and_invoice_with_payment_cannot_be_voided(): void
    {
        $this->issue('میزان ۱۴۰۵');
        $student = $this->students[0]->student;
        $invoice = StudentInvoice::where('student_id', $student->id)->sole();
        $this->pay($student, '400');
        $payment = Payment::sole();

        $this->actingAs($this->accountant)->patch(route('finance.invoices.void', $invoice), ['reason' => 'اشتباه'])
            ->assertSessionHas('error', __('finance.invoice_has_payments'));

        $this->actingAs($this->accountant)->patch(route('finance.payments.void', $payment), [])->assertSessionHasErrors('reason');
        $this->actingAs($this->accountant)->patch(route('finance.payments.void', $payment), ['reason' => 'پول برگشت داده شد'])->assertSessionHas('success');
        $this->assertNotNull($payment->fresh()->voided_at);
        $this->assertSame(100000, app(FinanceService::class)->invoicesFor($student, onlyOpen: true)->sum('remaining_cents'));

        $this->actingAs($this->accountant)->patch(route('finance.invoices.void', $invoice), ['reason' => 'بل اشتباه'])->assertSessionHas('success');
        $this->assertNotNull($invoice->fresh()->voided_at);
        $this->assertTrue(ActivityLog::where('action', 'payment.voided')->exists());

        // بعد از باطل شدن، همان دوره دوباره صادر می‌شود
        $this->issue('میزان ۱۴۰۵', [$this->section->id]);
        $this->assertSame(2, StudentInvoice::where('student_id', $student->id)->count());
    }

    public function test_discount_needs_reason_and_cannot_exceed_remaining(): void
    {
        $this->issue('میزان ۱۴۰۵');
        $student = $this->students[1]->student;
        $invoice = StudentInvoice::where('student_id', $student->id)->sole();
        $this->pay($student, '300');

        $this->actingAs($this->accountant)->patch(route('finance.invoices.discount', $invoice), ['discount' => '200'])
            ->assertSessionHas('error', __('finance.discount_reason_required'));
        $this->actingAs($this->accountant)->patch(route('finance.invoices.discount', $invoice), ['discount' => '800', 'discount_reason' => 'یتیم'])
            ->assertSessionHas('error', __('finance.discount_too_high'));
        $this->actingAs($this->accountant)->patch(route('finance.invoices.discount', $invoice), ['discount' => '700', 'discount_reason' => 'یتیم'])
            ->assertSessionHas('success');

        $this->assertSame(0, app(FinanceService::class)->invoicesFor($student, onlyOpen: true)->count());
        $log = ActivityLog::where('action', 'invoice.discounted')->sole();
        $this->assertSame('0', $log->old_values['discount']);
        $this->assertSame('700', $log->new_values['discount']);
    }

    public function test_single_invoice_from_student_profile(): void
    {
        $student = $this->students[2]->student;
        $this->actingAs($this->accountant)->get(route('students.show', $student))
            ->assertOk()->assertSee(__('finance.tab'))->assertSee(__('finance.issue_single'));

        $payload = ['_modal' => 'invoiceCreate', 'fee_type_id' => $this->monthly->id, 'period_label' => 'قوس ۱۴۰۵', 'amount' => '1000', 'discount' => '250', 'discount_reason' => 'خواهر و برادر'];
        $this->actingAs($this->accountant)->post(route('finance.invoices.single', $student), $payload)->assertSessionHas('success');
        $invoice = StudentInvoice::where('student_id', $student->id)->sole();
        $this->assertSame('250.00', $invoice->discount);

        $this->actingAs($this->accountant)->post(route('finance.invoices.single', $student), $payload)
            ->assertSessionHas('error', __('finance.invoice_duplicate', ['type' => $this->monthly->name, 'period' => 'قوس ۱۴۰۵']));
    }

    public function test_debtors_list_and_csv(): void
    {
        $this->issue('میزان ۱۴۰۵');
        $this->pay($this->students[0]->student, '1000');
        $this->pay($this->students[1]->student, '400');

        $this->actingAs($this->accountant)->get(route('finance.debtors.index'))
            ->assertOk()->assertSee('شاگرددوم')->assertSee('شاگردسوم')->assertDontSee('شاگرداول')
            ->assertSee(__('finance.debtors_total', ['amount' => '۱٬۶۰۰ افغانی']));

        $csv = $this->actingAs($this->accountant)->get(route('finance.debtors.export'))->assertOk()->streamedContent();
        $this->assertStringContainsString(',1000,0,400,600', $csv);
    }

    public function test_fee_setup_saves_rates_and_is_for_finance_managers(): void
    {
        $grade8 = $this->noRateSection->grade_level_id;
        $this->actingAs($this->accountant)->put(route('finance.setup.rates'), ['rates' => [$this->monthly->id => [$grade8 => '۱٬۲۰۰', $this->section->grade_level_id => '']]])
            ->assertSessionHas('success');

        $this->assertSame('1200.00', FeeRate::where('grade_level_id', $grade8)->value('amount'));
        $this->assertFalse(FeeRate::where('grade_level_id', $this->section->grade_level_id)->exists());

        $this->actingAs($this->accountant)->post(route('finance.setup.types.store'), ['name' => 'فیس کتاب', 'period' => 'annual', 'sort_order' => 3])->assertRedirect();
        $this->assertSame('annual', FeeType::where('name', 'فیس کتاب')->sole()->period->value);

        foreach ([Role::Office, Role::Teacher] as $role) {
            $this->actingAs($this->userWithRole($role))->get(route('finance.payments.index'))->assertForbidden();
        }
    }
}
