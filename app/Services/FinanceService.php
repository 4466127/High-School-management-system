<?php

namespace App\Services;

use App\Enums\EnrollmentStatus;
use App\Enums\FeePeriod;
use App\Enums\TransactionType;
use App\Models\AcademicYear;
use App\Models\Enrollment;
use App\Models\FeeRate;
use App\Models\FeeType;
use App\Models\Payment;
use App\Models\PaymentAllocation;
use App\Models\SchoolTransaction;
use App\Models\Section;
use App\Models\Student;
use App\Models\StudentInvoice;
use App\Models\User;
use App\Support\Digits;
use App\Support\JalaliDate;
use App\Support\Money;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

/**
 * مالی مکتب: صدور بل، تخفیف، پرداخت و رسید، باطل کردن، بدهکاران، درآمد و مصرف.
 * قاعده‌ها: هیچ سطری حذف نمی‌شود؛ جمع تخصیص‌های هر رسید = مبلغ رسید؛ پرداخت بیشتر از مانده پذیرفته نمی‌شود؛
 * شماره‌ی رسید پشت‌سرهم؛ هر عملیات در transaction و با ثبت رویداد.
 */
class FinanceService
{
    public function __construct(private readonly ActivityLogger $logger) {}

    // ------------------------------------------------------------------ بل‌ها

    /**
     * دوره‌های قابل انتخاب: فیس ماهوار ← ماه‌های سال تحصیلی («حمل ۱۴۰۵»)؛ سالانه و یک‌بار ← «سال ۱۴۰۵».
     *
     * @return list<string>
     */
    public static function periodOptions(FeeType $type, AcademicYear $year): array
    {
        if ($type->period !== FeePeriod::Monthly) {
            return [__('finance.year_period', ['year' => Digits::toPersian($year->name)])];
        }

        return array_map(
            fn ($m) => JalaliDate::monthName($m[1]).' '.Digits::toPersian((string) $m[0]),
            JalaliDate::monthsBetween($year->start_date, $year->end_date),
        );
    }

    /** مبلغ وارد‌شده‌ی کاربر («۱٬۲۵۰»، «1,250.50») ← پول؛ نادرست ← null */
    public static function parseAmount(?string $value): ?int
    {
        $clean = str_replace([',', '٬', ' ', "\u{066C}"], '', Digits::toLatin(trim((string) $value)));
        if ($clean === '' || ! preg_match('/^\d{1,9}(\.\d{1,2})?$/', $clean)) {
            return null;
        }

        return Money::toCents($clean);
    }

    public function rate(FeeType $type, int $yearId, int $gradeId): ?string
    {
        return FeeRate::where('fee_type_id', $type->id)->where('academic_year_id', $yearId)->where('grade_level_id', $gradeId)->value('amount');
    }

    /**
     * صدور گروهی برای دانش‌آموزان فعال چند بخش با نرخ صنف هر بخش. بل تکراری (همان نوع و دوره) ساخته نمی‌شود.
     *
     * @param  Collection<int, Section>  $sections
     * @return array{created: int, skipped: int, no_rate: int}
     */
    public function issueGroup(FeeType $type, string $period, Collection $sections, ?string $dueDate, User $user): array
    {
        return DB::transaction(function () use ($type, $period, $sections, $dueDate, $user) {
            $summary = ['created' => 0, 'skipped' => 0, 'no_rate' => 0];

            foreach ($sections as $section) {
                $enrollments = $section->activeEnrollments()->get();
                $rate = $this->rate($type, $section->academic_year_id, $section->grade_level_id);
                if ($rate === null || Money::toCents($rate) <= 0) {
                    $summary['no_rate'] += $enrollments->count();

                    continue;
                }

                $existing = StudentInvoice::active()
                    ->where('fee_type_id', $type->id)->where('period_label', $period)
                    ->whereIn('enrollment_id', $enrollments->pluck('id')->all() ?: [0])
                    ->lockForUpdate()
                    ->pluck('enrollment_id')->all();

                foreach ($enrollments as $enrollment) {
                    if (in_array($enrollment->id, $existing, true)) {
                        $summary['skipped']++;

                        continue;
                    }
                    StudentInvoice::create([
                        'enrollment_id' => $enrollment->id, 'student_id' => $enrollment->student_id, 'fee_type_id' => $type->id,
                        'period_label' => $period, 'amount' => $rate, 'discount' => 0, 'due_date' => $dueDate, 'created_by' => $user->id,
                    ]);
                    $summary['created']++;
                }
            }

            $this->logger->log('invoice.issued_group', $type, $type->name.' — '.$period, new: $summary, user: $user);

            return $summary;
        });
    }

    public function issueSingle(Enrollment $enrollment, FeeType $type, string $period, int $amountCents, int $discountCents, ?string $discountReason, ?string $dueDate, User $user): StudentInvoice
    {
        if ($discountCents > $amountCents) {
            throw ValidationException::withMessages(['discount' => __('finance.discount_too_high')]);
        }

        return DB::transaction(function () use ($enrollment, $type, $period, $amountCents, $discountCents, $discountReason, $dueDate, $user) {
            $duplicate = StudentInvoice::active()->where('enrollment_id', $enrollment->id)
                ->where('fee_type_id', $type->id)->where('period_label', $period)->lockForUpdate()->exists();
            if ($duplicate) {
                throw ValidationException::withMessages(['period_label' => __('finance.invoice_duplicate', ['type' => $type->name, 'period' => $period])]);
            }

            $invoice = StudentInvoice::create([
                'enrollment_id' => $enrollment->id, 'student_id' => $enrollment->student_id, 'fee_type_id' => $type->id,
                'period_label' => $period, 'amount' => Money::fromCents($amountCents), 'discount' => Money::fromCents($discountCents),
                'discount_reason' => $discountCents > 0 ? $discountReason : null, 'due_date' => $dueDate, 'created_by' => $user->id,
            ]);

            $this->logger->log('invoice.issued', $invoice, $enrollment->student->full_name.' — '.$type->name.' — '.$period, new: [
                'amount' => Money::plain($invoice->amount), 'discount' => Money::plain($invoice->discount),
            ], user: $user);

            return $invoice;
        });
    }

    public function setDiscount(StudentInvoice $invoice, int $discountCents, ?string $reason, User $user): void
    {
        DB::transaction(function () use ($invoice, $discountCents, $reason, $user) {
            $invoice = StudentInvoice::whereKey($invoice->id)->lockForUpdate()->firstOrFail();
            if ($invoice->isVoided()) {
                throw ValidationException::withMessages(['discount' => __('finance.invoice_voided')]);
            }
            if ($discountCents < 0 || $discountCents > Money::toCents($invoice->amount) - $invoice->paidCents()) {
                throw ValidationException::withMessages(['discount' => __('finance.discount_too_high')]);
            }

            $old = ['discount' => Money::plain($invoice->discount), 'discount_reason' => $invoice->discount_reason];
            $invoice->update(['discount' => Money::fromCents($discountCents), 'discount_reason' => $discountCents > 0 ? $reason : null]);
            $this->logger->log('invoice.discounted', $invoice, $this->invoiceLabel($invoice), $old,
                ['discount' => Money::plain($invoice->discount), 'discount_reason' => $invoice->discount_reason], $user);
        });
    }

    public function voidInvoice(StudentInvoice $invoice, string $reason, User $user): void
    {
        DB::transaction(function () use ($invoice, $reason, $user) {
            $invoice = StudentInvoice::whereKey($invoice->id)->lockForUpdate()->firstOrFail();
            if ($invoice->isVoided()) {
                return;
            }
            if ($invoice->paidCents() > 0) {
                throw ValidationException::withMessages(['reason' => __('finance.invoice_has_payments')]);
            }

            $invoice->forceFill(['voided_at' => now(), 'voided_by' => $user->id, 'void_reason' => $reason])->save();
            $this->logger->log('invoice.voided', $invoice, $this->invoiceLabel($invoice).' — '.$reason,
                ['amount' => Money::plain($invoice->amount)], ['void_reason' => $reason], $user);
        });
    }

    /**
     * بل‌های فعال یک دانش‌آموز با ستون‌های paid و remaining (به افغانی)، کهنه‌ترین اول.
     *
     * @return Collection<int, StudentInvoice>
     */
    public function invoicesFor(Student $student, bool $onlyOpen = false): Collection
    {
        $invoices = StudentInvoice::query()
            ->with(['feeType', 'enrollment.section.gradeLevel'])
            ->where('student_id', $student->id)
            ->withPaid()
            ->orderByRaw('voided_at IS NOT NULL')
            ->orderByRaw('due_date IS NULL, due_date')
            ->orderBy('id')
            ->get()
            ->each(function (StudentInvoice $i) {
                $i->setAttribute('remaining_cents', $i->isVoided() ? 0 : $i->netCents() - Money::toCents($i->paid));
            });

        return $onlyOpen ? $invoices->filter(fn ($i) => ! $i->isVoided() && $i->remaining_cents > 0)->values() : $invoices;
    }

    // ------------------------------------------------------------------ پرداخت

    /**
     * ثبت پرداخت و رسید. allocations خالی ← به کهنه‌ترین بل‌ها تقسیم می‌شود.
     *
     * @param  array<int|string, int>|null  $allocations  invoice_id => پول (سنت)
     */
    public function pay(Student $student, int $amountCents, string $paidOn, ?string $payer, ?string $note, ?array $allocations, User $user): Payment
    {
        if ($amountCents <= 0) {
            throw ValidationException::withMessages(['amount' => __('finance.amount_positive')]);
        }

        return DB::transaction(function () use ($student, $amountCents, $paidOn, $payer, $note, $allocations, $user) {
            StudentInvoice::active()->where('student_id', $student->id)->lockForUpdate()->get();
            $open = $this->invoicesFor($student, onlyOpen: true)->keyBy('id');
            $totalDue = $open->sum('remaining_cents');

            if ($amountCents > $totalDue) {
                throw ValidationException::withMessages(['amount' => __('finance.amount_over_due', ['due' => Money::format(Money::fromCents($totalDue))])]);
            }

            $allocations = array_filter(array_map('intval', $allocations ?? []), fn ($c) => $c > 0);
            if ($allocations === []) {
                $left = $amountCents;
                foreach ($open as $invoice) {
                    if ($left <= 0) {
                        break;
                    }
                    $part = min($left, $invoice->remaining_cents);
                    $allocations[$invoice->id] = $part;
                    $left -= $part;
                }
            }

            foreach ($allocations as $invoiceId => $cents) {
                $invoice = $open->get($invoiceId);
                if (! $invoice || $cents > $invoice->remaining_cents) {
                    throw ValidationException::withMessages(['allocations' => __('finance.allocation_invalid')]);
                }
            }
            if (array_sum($allocations) !== $amountCents) {
                throw ValidationException::withMessages(['allocations' => __('finance.allocation_sum', ['amount' => Money::format(Money::fromCents($amountCents))])]);
            }

            $payment = Payment::create([
                'receipt_no' => $this->nextReceiptNo(),
                'student_id' => $student->id,
                'paid_on' => $paidOn,
                'amount' => Money::fromCents($amountCents),
                'payer_name' => $payer,
                'note' => $note,
                'received_by' => $user->id,
            ]);
            foreach ($allocations as $invoiceId => $cents) {
                PaymentAllocation::create(['payment_id' => $payment->id, 'student_invoice_id' => $invoiceId, 'amount' => Money::fromCents($cents)]);
            }

            $this->logger->log('payment.received', $payment, __('finance.receipt').' '.Digits::toPersian((string) $payment->receipt_no).' — '.$student->full_name, new: [
                'receipt_no' => $payment->receipt_no,
                'amount' => Money::plain($payment->amount),
                'invoices' => collect($allocations)->map(fn ($c, $id) => $open[$id]->feeType->name.' '.$open[$id]->period_label.': '.Money::plain(Money::fromCents($c)))->values()->join('، '),
            ], user: $user);

            return $payment;
        });
    }

    public function voidPayment(Payment $payment, string $reason, User $user): void
    {
        DB::transaction(function () use ($payment, $reason, $user) {
            $payment = Payment::whereKey($payment->id)->lockForUpdate()->firstOrFail();
            if ($payment->isVoided()) {
                return;
            }
            $payment->forceFill(['voided_at' => now(), 'voided_by' => $user->id, 'void_reason' => $reason])->save();
            $this->logger->log('payment.voided', $payment, __('finance.receipt').' '.Digits::toPersian((string) $payment->receipt_no).' — '.$reason,
                ['amount' => Money::plain($payment->amount)], ['void_reason' => $reason], $user);
        });
    }

    /** شماره‌ی رسید بعدی (داخل transaction؛ قفل روی آخرین رسید) */
    public function nextReceiptNo(): int
    {
        return ((int) Payment::query()->lockForUpdate()->max('receipt_no')) + 1;
    }

    // ------------------------------------------------------------------ بدهکاران

    /**
     * شاملان سال با مانده‌ی بیشتر از صفر.
     *
     * @param  list<int>|null  $sectionIds
     * @return Collection<int, object{enrollment: Enrollment, total: int, discount: int, paid: int, remaining: int, invoices: int}>
     */
    public function debtors(AcademicYear $year, ?array $sectionIds = null): Collection
    {
        $paid = DB::table('payment_allocations')
            ->join('payments', 'payments.id', '=', 'payment_allocations.payment_id')
            ->whereNull('payments.voided_at')
            ->groupBy('payment_allocations.student_invoice_id')
            ->selectRaw('payment_allocations.student_invoice_id, SUM(payment_allocations.amount) as paid');

        $rows = DB::table('student_invoices')
            ->join('enrollments', 'enrollments.id', '=', 'student_invoices.enrollment_id')
            ->leftJoinSub($paid, 'pa', 'pa.student_invoice_id', '=', 'student_invoices.id')
            ->whereNull('student_invoices.voided_at')
            ->where('enrollments.academic_year_id', $year->id)
            ->when($sectionIds !== null, fn ($q) => $q->whereIn('enrollments.section_id', $sectionIds ?: [0]))
            ->groupBy('student_invoices.enrollment_id')
            ->selectRaw('student_invoices.enrollment_id, COUNT(*) as invoices, SUM(student_invoices.amount) as total, SUM(student_invoices.discount) as discount,
                SUM(COALESCE(pa.paid, 0)) as paid, SUM(student_invoices.amount - student_invoices.discount - COALESCE(pa.paid, 0)) as remaining')
            ->havingRaw('SUM(student_invoices.amount - student_invoices.discount - COALESCE(pa.paid, 0)) > 0')
            ->get();

        $enrollments = Enrollment::with(['student', 'section.gradeLevel'])->whereIn('id', $rows->pluck('enrollment_id')->all() ?: [0])->get()->keyBy('id');

        return $rows->map(fn ($r) => (object) [
            'enrollment' => $enrollments[$r->enrollment_id],
            'invoices' => (int) $r->invoices,
            'total' => Money::toCents($r->total),
            'discount' => Money::toCents($r->discount),
            'paid' => Money::toCents($r->paid),
            'remaining' => Money::toCents($r->remaining),
        ])->sortByDesc('remaining')->values();
    }

    // ------------------------------------------------------------------ درآمد و مصرف

    public function recordTransaction(array $data, User $user): SchoolTransaction
    {
        return DB::transaction(function () use ($data, $user) {
            $tx = SchoolTransaction::create($data + ['recorded_by' => $user->id]);
            $tx->load('category');
            $this->logger->log('transaction.recorded', $tx, $tx->type->label().' — '.$tx->category->name, new: [
                'amount' => Money::plain($tx->amount), 'party_name' => $tx->party_name,
            ], user: $user);

            return $tx;
        });
    }

    public function voidTransaction(SchoolTransaction $tx, string $reason, User $user): void
    {
        DB::transaction(function () use ($tx, $reason, $user) {
            if ($tx->isVoided()) {
                return;
            }
            $tx->forceFill(['voided_at' => now(), 'voided_by' => $user->id, 'void_reason' => $reason])->save();
            $this->logger->log('transaction.voided', $tx, $tx->type->label().' — '.$reason, ['amount' => Money::plain($tx->amount)], ['void_reason' => $reason], $user);
        });
    }

    /** جمع درآمدها، مصارف و دریافت فیس در یک بازه (به پول) */
    public function totals(string $from, string $to): array
    {
        $sum = fn (TransactionType $type) => Money::toCents(SchoolTransaction::whereNull('voided_at')->where('type', $type)->whereBetween('date', [$from, $to])->sum('amount'));

        return [
            'income' => $sum(TransactionType::Income),
            'expense' => $sum(TransactionType::Expense),
            'fees' => Money::toCents(Payment::whereNull('voided_at')->whereBetween('paid_on', [$from, $to])->sum('amount')),
        ];
    }

    private function invoiceLabel(StudentInvoice $invoice): string
    {
        $invoice->loadMissing(['student', 'feeType']);

        return $invoice->student->full_name.' — '.$invoice->feeType->name.' — '.$invoice->period_label;
    }

    /** دانش‌آموز شامل در سال جاری؟ (برای صدور بل تکی) */
    public function currentEnrollment(Student $student): ?Enrollment
    {
        $year = AcademicYear::current();

        return $year ? Enrollment::where('student_id', $student->id)->where('academic_year_id', $year->id)
            ->whereIn('status', [EnrollmentStatus::Active])->first() : null;
    }
}
