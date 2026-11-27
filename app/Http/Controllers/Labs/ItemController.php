<?php

namespace App\Http\Controllers\Labs;

use App\Enums\LabEventType;
use App\Enums\LabItemKind;
use App\Http\Controllers\Controller;
use App\Http\Requests\Labs\LabItemRequest;
use App\Models\Lab;
use App\Models\LabItem;
use App\Rules\ValidJalaliDate;
use App\Services\LabService;
use App\Support\Csv;
use App\Support\JalaliDate;
use App\Support\Money;
use App\Support\Quantity;
use Closure;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Illuminate\View\View;
use Symfony\Component\HttpFoundation\StreamedResponse;

/**
 * موجودی لابراتوار (مرحله‌ی ۱۸). دیدن: labs.view؛ ثبت، ویرایش و ورود/خروج: labs.manage (در مسیرها).
 * فهرست با فیلتر لابراتوار، نوع و هشدار (کمبود، تاریخ‌گذشته، نزدیک ختم، خراب)؛ چاپ = فورم شمارش موجودی (?print=1)؛ CSV.
 */
class ItemController extends Controller
{
    public function __construct(private readonly LabService $labs) {}

    public function index(Request $request): View
    {
        $print = $request->boolean('print');
        $items = $this->filtered($request)->with('lab')->orderBy('lab_id')->orderBy('kind')->orderBy('name');
        $base = LabItem::query()->where('is_active', true);

        return view('labs.items.index', [
            'items' => $print ? $items->get() : $items->paginate(30)->withQueryString(),
            'labs' => Lab::orderBy('name')->get(),
            'filters' => $request->only(['lab', 'kind', 'alert', 'q', 'inactive']),
            'alerts' => collect(['low', 'expired', 'expiring', 'damaged'])->mapWithKeys(fn ($a) => [$a => (clone $base)->alert($a)->count()])->all(),
            'canManage' => $request->user()->hasPermission('labs.manage'),
            'print' => $print,
        ]);
    }

    public function export(Request $request): StreamedResponse
    {
        $rows = $this->filtered($request)->with('lab')->orderBy('lab_id')->orderBy('name')->get()->map(fn (LabItem $i) => [
            $i->lab->name, $i->code, $i->name, $i->kind->label(), $i->unit->label(), Quantity::plain($i->quantity), Quantity::plain($i->damaged_quantity),
            $i->min_quantity !== null ? Quantity::plain($i->min_quantity) : '', $i->location, $i->hazard?->label(),
            $i->expires_on ? JalaliDate::format($i->expires_on) : '', $i->unit_price !== null ? Money::plain($i->unit_price) : '', $i->source->label(), $i->specification,
        ]);

        return Csv::download('lab-inventory.csv', array_values(__('labs.export_columns')), $rows);
    }

    public function create(Request $request): View
    {
        return view('labs.items.form', [
            'item' => new LabItem(['lab_id' => (int) $request->query('lab') ?: null, 'kind' => 'equipment', 'unit' => 'piece', 'source' => 'purchase']),
            'labs' => Lab::active()->orderBy('name')->pluck('name', 'id')->all(),
        ]);
    }

    public function store(LabItemRequest $request): RedirectResponse
    {
        $item = $this->labs->createItem($request->itemData(), $request->initialQuantity(), $request->user());

        return redirect()->route('labs.items.show', $item)->with('success', __('labs.item_created'));
    }

    public function show(Request $request, LabItem $item): View
    {
        $item->load('lab');

        return view('labs.items.show', [
            'item' => $item,
            'events' => $item->events()->with(['user', 'activity.section.gradeLevel'])->paginate(30),
            'canManage' => $request->user()->hasPermission('labs.manage'),
        ]);
    }

    public function edit(LabItem $item): View
    {
        return view('labs.items.form', ['item' => $item, 'labs' => Lab::orderBy('name')->pluck('name', 'id')->all()]);
    }

    public function update(LabItemRequest $request, LabItem $item): RedirectResponse
    {
        $this->labs->updateItem($item, $request->itemData(), $request->user());

        return redirect()->route('labs.items.show', $item)->with('success', __('labs.saved'));
    }

    /** یک رویداد دفتر: رسید، مصرف، خراب، ترمیم، از بین رفتن، گم، دور انداختن، شمارش */
    public function move(Request $request, LabItem $item): RedirectResponse
    {
        $data = $request->validate([
            'type' => ['required', Rule::enum(LabEventType::class)],
            'amount' => ['required', fn (string $a, mixed $v, Closure $fail) => Quantity::parse((string) $v) === null ? $fail(__('labs.quantity_invalid')) : null],
            'event_date' => ['nullable', new ValidJalaliDate(notFuture: true)],
            'note' => ['nullable', 'string', 'max:300'],
        ], [], __('labs.attributes'));
        $type = LabEventType::from($data['type']);
        $this->labs->move($item, $type, Quantity::parse((string) $data['amount']), JalaliDate::parse($data['event_date'] ?? null)?->toDateString(), $data['note'] ?? null, $request->user());

        return back()->with('success', __('labs.moved', ['type' => $type->label()]));
    }

    private function filtered(Request $request): Builder
    {
        return LabItem::query()
            ->when(! $request->boolean('inactive'), fn ($q) => $q->where('is_active', true))
            ->when((int) $request->query('lab'), fn ($q, $lab) => $q->where('lab_id', $lab))
            ->when(LabItemKind::tryFrom((string) $request->query('kind')), fn ($q, $k) => $q->where('kind', $k->value))
            ->when(in_array($request->query('alert'), ['low', 'expired', 'expiring', 'damaged'], true), fn ($q) => $q->alert((string) $request->query('alert')))
            ->search($request->query('q'));
    }
}
