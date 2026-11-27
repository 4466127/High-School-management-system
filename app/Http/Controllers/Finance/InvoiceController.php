<?php

namespace App\Http\Controllers\Finance;

use App\Http\Controllers\Controller;
use App\Models\AcademicYear;
use App\Models\FeeType;
use App\Models\Section;
use App\Models\Student;
use App\Models\StudentInvoice;
use App\Rules\ValidAmount;
use App\Rules\ValidJalaliDate;
use App\Services\FinanceService;
use App\Support\Digits;
use App\Support\JalaliDate;
use App\Support\Money;
use App\Support\Paging;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;
use Illuminate\View\View;

/** بل‌ها: فهرست با فیلتر، صدور گروهی و تکی، تخفیف، باطل کردن. */
class InvoiceController extends Controller
{
    public function __construct(private readonly FinanceService $finance) {}

    public function index(Request $request): View
    {
        $year = AcademicYear::current();
        $sections = $year ? Section::with('gradeLevel')->where('academic_year_id', $year->id)->ordered()->get() : collect();
        $status = in_array($request->query('status'), ['open', 'paid', 'voided', 'all'], true) ? $request->query('status') : 'open';

        $query = StudentInvoice::query()
            ->with(['student', 'feeType', 'enrollment.section.gradeLevel'])
            ->withPaid()
            ->when($request->integer('section'), fn ($q, $id) => $q->whereHas('enrollment', fn ($e) => $e->where('section_id', $id)))
            ->when($request->integer('fee_type'), fn ($q, $id) => $q->where('fee_type_id', $id))
            ->when($request->filled('period'), fn ($q) => $q->where('period_label', Digits::toPersian((string) $request->query('period'))))
            ->when($request->filled('q'), fn ($q) => $q->whereHas('student', fn ($s) => $s->search($request->query('q'))));

        match ($status) {
            'open' => $query->open(),
            'paid' => $query->settled(),
            'voided' => $query->whereNotNull('student_invoices.voided_at'),
            default => null,
        };

        $all = (clone $query)->get();
        $active = $all->whereNull('voided_at');

        return view('finance.invoices.index', [
            'invoices' => $query->orderByDesc('student_invoices.id')->paginate(Paging::size())->withQueryString(),
            'sections' => $sections,
            'types' => FeeType::orderBy('sort_order')->get(),
            'periods' => StudentInvoice::query()->distinct()->orderBy('period_label')->pluck('period_label'),
            'status' => $status,
            'totals' => [
                'count' => $all->count(),
                'net' => $active->sum(fn ($i) => $i->netCents()),
                'paid' => $active->sum(fn ($i) => Money::toCents($i->paid)),
            ],
        ]);
    }

    public function create(Request $request): View
    {
        Gate::authorize('finance.manage');
        $year = AcademicYear::current();
        abort_unless($year, 404);

        $types = FeeType::where('is_active', true)->orderBy('sort_order')->get();
        $type = $types->firstWhere('id', $request->integer('fee_type')) ?? $types->first();

        return view('finance.invoices.create', [
            'year' => $year,
            'types' => $types,
            'type' => $type,
            'periods' => $type ? FinanceService::periodOptions($type, $year) : [],
            'sections' => Section::with('gradeLevel')->withCount('activeEnrollments')->where('academic_year_id', $year->id)->ordered()->get(),
            'rates' => $type ? $type->rates()->where('academic_year_id', $year->id)->pluck('amount', 'grade_level_id') : collect(),
        ]);
    }

    public function store(Request $request): RedirectResponse
    {
        Gate::authorize('finance.manage');
        $year = AcademicYear::current();
        abort_unless($year, 404);

        $data = $request->validate([
            'fee_type_id' => ['required', Rule::exists('fee_types', 'id')],
            'period_label' => ['required', 'string', 'max:50'],
            'section_ids' => ['required', 'array', 'min:1'],
            'section_ids.*' => ['integer', Rule::exists('sections', 'id')->where('academic_year_id', $year->id)],
            'due_date' => ['nullable', new ValidJalaliDate],
        ], [], __('finance.attributes'));

        $type = FeeType::findOrFail($data['fee_type_id']);
        // NormalizeInput ارقام را لاتین کرده است؛ دوره‌ها با ارقام فارسی ذخیره می‌شوند
        $data['period_label'] = Digits::toPersian($data['period_label']);
        abort_unless(in_array($data['period_label'], FinanceService::periodOptions($type, $year), true), 422);

        $summary = $this->finance->issueGroup($type, $data['period_label'],
            Section::whereIn('id', $data['section_ids'])->get(),
            JalaliDate::parse($data['due_date'] ?? null)?->toDateString(), $request->user());

        return redirect()->route('finance.invoices.index', ['fee_type' => $type->id, 'period' => $data['period_label'], 'status' => 'all'])
            ->with($summary['created'] > 0 ? 'success' : 'warning', __('finance.issued_group', array_map(fn ($n) => Digits::toPersian((string) $n), $summary)));
    }

    /** بل تکی از پرونده‌ی دانش‌آموز */
    public function storeSingle(Request $request, Student $student): RedirectResponse
    {
        Gate::authorize('finance.manage');
        $year = AcademicYear::current();
        $enrollment = $this->finance->currentEnrollment($student);
        if (! $year || ! $enrollment) {
            return back()->with('error', __('finance.not_enrolled'));
        }

        $data = $request->validate([
            'fee_type_id' => ['required', Rule::exists('fee_types', 'id')],
            'period_label' => ['required', 'string', 'max:50'],
            'amount' => ['required', new ValidAmount],
            'discount' => ['nullable', new ValidAmount(allowZero: true)],
            'discount_reason' => ['nullable', 'string', 'max:255'],
            'due_date' => ['nullable', new ValidJalaliDate],
        ], [], __('finance.attributes'));

        $type = FeeType::findOrFail($data['fee_type_id']);
        $data['period_label'] = Digits::toPersian($data['period_label']);
        $discount = FinanceService::parseAmount($data['discount'] ?? '0') ?? 0;
        if (! in_array($data['period_label'], FinanceService::periodOptions($type, $year), true)) {
            return back()->withInput()->with('error', __('finance.period_invalid'));
        }
        if ($discount > 0 && trim((string) ($data['discount_reason'] ?? '')) === '') {
            return back()->withInput()->with('error', __('finance.discount_reason_required'));
        }

        try {
            $this->finance->issueSingle($enrollment, $type, $data['period_label'],
                FinanceService::parseAmount($data['amount']), $discount,
                $data['discount_reason'] ?? null, JalaliDate::parse($data['due_date'] ?? null)?->toDateString(), $request->user());
        } catch (ValidationException $e) {
            return back()->withErrors($e->errors())->withInput()->with('error', collect($e->errors())->flatten()->first());
        }

        return redirect()->to(route('students.show', $student).'#fees')->with('success', __('finance.invoice_issued'));
    }

    public function discount(Request $request, StudentInvoice $invoice): RedirectResponse
    {
        Gate::authorize('finance.manage');
        $data = $request->validate([
            'discount' => ['required', new ValidAmount(allowZero: true)],
            'discount_reason' => ['nullable', 'string', 'max:255'],
        ], [], __('finance.attributes'));
        $cents = FinanceService::parseAmount($data['discount']);
        if ($cents > 0 && trim((string) ($data['discount_reason'] ?? '')) === '') {
            return back()->with('error', __('finance.discount_reason_required'));
        }

        try {
            $this->finance->setDiscount($invoice, $cents, $data['discount_reason'] ?? null, $request->user());
        } catch (ValidationException $e) {
            return back()->with('error', collect($e->errors())->flatten()->first());
        }

        return back()->with('success', __('finance.discount_saved'));
    }

    public function void(Request $request, StudentInvoice $invoice): RedirectResponse
    {
        Gate::authorize('finance.manage');
        $data = $request->validate(['reason' => ['required', 'string', 'max:500']], [], ['reason' => __('students.reason')]);

        try {
            $this->finance->voidInvoice($invoice, $data['reason'], $request->user());
        } catch (ValidationException $e) {
            return back()->with('error', collect($e->errors())->flatten()->first());
        }

        return back()->with('success', __('finance.invoice_voided_ok'));
    }
}
