<?php

namespace App\Http\Controllers\Finance;

use App\Enums\TransactionType;
use App\Http\Controllers\Controller;
use App\Models\FinanceCategory;
use App\Models\SchoolTransaction;
use App\Models\Setting;
use App\Rules\ValidAmount;
use App\Rules\ValidJalaliDate;
use App\Services\ActivityLogger;
use App\Services\FinanceService;
use App\Support\Csv;
use App\Support\Digits;
use App\Support\JalaliDate;
use App\Support\Money;
use App\Support\Paging;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;
use Illuminate\Validation\Rule;
use Illuminate\View\View;
use Symfony\Component\HttpFoundation\StreamedResponse;

/** درآمد و مصرف عمومی مکتب (دفتر روشن): فهرست با فیلتر و جمع، ثبت، باطل کردن، دسته‌ها. */
class LedgerController extends Controller
{
    public function __construct(
        private readonly FinanceService $finance,
        private readonly ActivityLogger $logger,
    ) {}

    public function index(Request $request): View
    {
        return view('finance.ledger.index', $this->load($request) + ['print' => $request->boolean('print')]);
    }

    public function export(Request $request): StreamedResponse
    {
        $rows = $this->load($request, paginate: false)['transactions']->map(fn (SchoolTransaction $t) => [
            Digits::toLatin(JalaliDate::format($t->date)), $t->type->label(), $t->category->name, Money::plain($t->amount),
            $t->party_name, $t->description, $t->recorder?->name, $t->isVoided() ? __('finance.voided').': '.$t->void_reason : '',
        ]);

        return Csv::download('school-ledger.csv', [
            __('finance.date'), __('finance.tx_type'), __('finance.category'), __('finance.amount'),
            __('finance.party'), __('finance.description'), __('finance.recorded_by'), __('finance.status'),
        ], $rows);
    }

    public function store(Request $request): RedirectResponse
    {
        Gate::authorize('finance.manage');
        $data = $request->validate([
            'type' => ['required', Rule::enum(TransactionType::class)],
            'finance_category_id' => ['required', Rule::exists('finance_categories', 'id')->where('type', $request->input('type'))->where('is_active', true)],
            'amount' => ['required', new ValidAmount],
            'date' => ['required', new ValidJalaliDate(notFuture: true)],
            'party_name' => ['nullable', 'string', 'max:150'],
            'description' => ['nullable', 'string', 'max:500'],
        ], [], __('finance.attributes'));

        $data['amount'] = Money::fromCents(FinanceService::parseAmount($data['amount']));
        $data['date'] = JalaliDate::parse($data['date'])->toDateString();
        $this->finance->recordTransaction($data, $request->user());

        return redirect()->route('finance.ledger.index')->with('success', __('finance.tx_saved'));
    }

    public function void(Request $request, SchoolTransaction $transaction): RedirectResponse
    {
        Gate::authorize('finance.manage');
        $data = $request->validate(['reason' => ['required', 'string', 'max:500']], [], ['reason' => __('students.reason')]);
        $this->finance->voidTransaction($transaction, $data['reason'], $request->user());

        return back()->with('success', __('finance.tx_voided'));
    }

    public function storeCategory(Request $request): RedirectResponse
    {
        Gate::authorize('finance.manage');
        $data = $request->validate([
            'type' => ['required', Rule::enum(TransactionType::class)],
            'name' => ['required', 'string', 'max:100', Rule::unique('finance_categories', 'name')->where('type', $request->input('type'))],
        ], [], __('finance.attributes'));
        $category = FinanceCategory::create($data + ['is_active' => true]);
        $this->logger->log('finance_category.created', $category, $category->type->label().' — '.$category->name);

        return redirect()->to(route('finance.ledger.index').'#categories-pane')->with('success', __('finance.category_saved'));
    }

    public function toggleCategory(FinanceCategory $category): RedirectResponse
    {
        Gate::authorize('finance.manage');
        $category->update(['is_active' => ! $category->is_active]);
        $this->logger->log('finance_category.updated', $category, $category->name, new: ['is_active' => $category->is_active]);

        return redirect()->to(route('finance.ledger.index').'#categories-pane')->with('success', __('finance.category_saved'));
    }

    private function load(Request $request, bool $paginate = true): array
    {
        $from = JalaliDate::parse($request->query('from'));
        $to = JalaliDate::parse($request->query('to'));
        $type = TransactionType::tryFrom((string) $request->query('type'));
        $showVoided = $request->boolean('voided');

        $query = SchoolTransaction::query()
            ->with(['category', 'recorder'])
            ->when($type, fn ($q) => $q->where('type', $type))
            ->when($request->integer('category'), fn ($q, $id) => $q->where('finance_category_id', $id))
            ->when($from, fn ($q) => $q->where('date', '>=', $from->toDateString()))
            ->when($to, fn ($q) => $q->where('date', '<=', $to->toDateString()))
            ->when(! $showVoided, fn ($q) => $q->whereNull('voided_at'))
            ->orderByDesc('date')->orderByDesc('id');

        $totals = $this->finance->totals(($from ?? JalaliDate::toGregorian(1300, 1, 1))->toDateString(), ($to ?? now()->addYears(5))->toDateString());

        return [
            'transactions' => $paginate ? $query->paginate(Paging::size())->withQueryString() : $query->get(),
            'categories' => FinanceCategory::orderBy('type')->orderBy('name')->get(),
            'totals' => $totals,
            'feesEnabled' => Setting::enabled('fees_enabled'),
            'filters' => ['from' => $request->query('from'), 'to' => $request->query('to'), 'type' => $type?->value, 'category' => $request->integer('category') ?: null, 'voided' => $showVoided],
        ];
    }
}
