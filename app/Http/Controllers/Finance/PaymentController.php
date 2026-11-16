<?php

namespace App\Http\Controllers\Finance;

use App\Enums\StudentStatus;
use App\Http\Controllers\Controller;
use App\Models\Payment;
use App\Models\Student;
use App\Rules\ValidAmount;
use App\Rules\ValidJalaliDate;
use App\Services\FinanceService;
use App\Support\Csv;
use App\Support\Digits;
use App\Support\JalaliDate;
use App\Support\Money;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;
use Illuminate\Validation\ValidationException;
use Illuminate\View\View;
use Symfony\Component\HttpFoundation\StreamedResponse;

/** رسیدها: فهرست، جست‌وجوی دانش‌آموز برای پرداخت تازه، ثبت پرداخت، چاپ رسید، باطل کردن. */
class PaymentController extends Controller
{
    public function __construct(private readonly FinanceService $finance) {}

    public function index(Request $request): View
    {
        $data = $this->receipts($request);

        $found = collect();
        if ($request->filled('student_q') && $request->user()->hasPermission('finance.manage')) {
            $found = Student::query()->with('currentEnrollment.section.gradeLevel')
                ->search($request->query('student_q'))
                ->whereIn('status', [StudentStatus::Active, StudentStatus::Graduated, StudentStatus::Transferred, StudentStatus::Dropped])
                ->orderBy('first_name')->limit(20)->get();
        }

        return view('finance.payments.index', $data + ['found' => $found]);
    }

    public function export(Request $request): StreamedResponse
    {
        $rows = $this->receipts($request, paginate: false)['payments']->map(fn (Payment $p) => [
            $p->receipt_no, Digits::toLatin(JalaliDate::format($p->paid_on)), $p->student->full_name, $p->student->father_name,
            Money::plain($p->amount), $p->payer_name, $p->receiver?->name, $p->isVoided() ? __('finance.voided').': '.$p->void_reason : '',
        ]);

        return Csv::download('receipts.csv', [
            __('finance.receipt_no'), __('finance.paid_on'), __('students.full_name'), __('students.father_name'),
            __('finance.amount'), __('finance.payer'), __('finance.received_by'), __('finance.status'),
        ], $rows);
    }

    public function create(Student $student): View
    {
        Gate::authorize('finance.manage');

        return view('finance.payments.create', [
            'student' => $student->load('currentEnrollment.section.gradeLevel'),
            'invoices' => $this->finance->invoicesFor($student, onlyOpen: true),
        ]);
    }

    public function store(Request $request, Student $student): RedirectResponse
    {
        Gate::authorize('finance.manage');
        $data = $request->validate([
            'amount' => ['required', new ValidAmount],
            'paid_on' => ['required', new ValidJalaliDate(notFuture: true)],
            'payer_name' => ['nullable', 'string', 'max:150'],
            'note' => ['nullable', 'string', 'max:500'],
            'allocations' => ['nullable', 'array'],
            'allocations.*' => ['nullable', new ValidAmount(allowZero: true)],
        ], [], __('finance.attributes'));

        $allocations = collect($data['allocations'] ?? [])->map(fn ($v) => FinanceService::parseAmount((string) $v) ?? 0)->filter()->all();

        try {
            $payment = $this->finance->pay($student, FinanceService::parseAmount($data['amount']),
                JalaliDate::parse($data['paid_on'])->toDateString(), $data['payer_name'] ?? null, $data['note'] ?? null,
                $allocations ?: null, $request->user());
        } catch (ValidationException $e) {
            return back()->withInput()->withErrors($e->errors())->with('error', collect($e->errors())->flatten()->first());
        }

        return redirect()->route('finance.payments.show', $payment)->with('success', __('finance.payment_saved', [
            'no' => Digits::toPersian((string) $payment->receipt_no), 'amount' => Money::format($payment->amount),
        ]));
    }

    /** رسید (قالب چاپ) */
    public function show(Payment $payment): View
    {
        $payment->load(['student', 'allocations.invoice.feeType', 'receiver', 'voidedBy']);

        return view('finance.payments.show', [
            'payment' => $payment,
            'remaining' => $this->finance->invoicesFor($payment->student, onlyOpen: true)->sum('remaining_cents'),
        ]);
    }

    public function void(Request $request, Payment $payment): RedirectResponse
    {
        Gate::authorize('finance.manage');
        $data = $request->validate(['reason' => ['required', 'string', 'max:500']], [], ['reason' => __('students.reason')]);
        $this->finance->voidPayment($payment, $data['reason'], $request->user());

        return back()->with('success', __('finance.payment_voided', ['no' => Digits::toPersian((string) $payment->receipt_no)]));
    }

    private function receipts(Request $request, bool $paginate = true): array
    {
        $from = JalaliDate::parse($request->query('from'));
        $to = JalaliDate::parse($request->query('to'));

        $query = Payment::query()
            ->with(['student', 'receiver'])
            ->when($from, fn ($q) => $q->where('paid_on', '>=', $from->toDateString()))
            ->when($to, fn ($q) => $q->where('paid_on', '<=', $to->toDateString()))
            ->when($request->filled('q'), function ($q) use ($request) {
                $term = Digits::toLatin(trim((string) $request->query('q')));
                $q->where(fn ($w) => $w->where('receipt_no', ctype_digit($term) ? (int) $term : 0)
                    ->orWhereHas('student', fn ($s) => $s->search($term)));
            });

        $total = Money::toCents((clone $query)->whereNull('voided_at')->sum('amount'));
        $query->orderByDesc('receipt_no');

        return [
            'payments' => $paginate ? $query->paginate(50)->withQueryString() : $query->get(),
            'total' => $total,
            'from' => $request->query('from'),
            'to' => $request->query('to'),
        ];
    }
}
