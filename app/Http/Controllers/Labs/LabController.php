<?php

namespace App\Http\Controllers\Labs;

use App\Enums\ActivityStatus;
use App\Enums\LabEventType;
use App\Enums\LabType;
use App\Enums\RoomType;
use App\Http\Controllers\Controller;
use App\Models\AcademicYear;
use App\Models\Lab;
use App\Models\LabItem;
use App\Models\LabItemEvent;
use App\Models\PracticalActivity;
use App\Models\Room;
use App\Models\Staff;
use App\Services\AttendanceService;
use App\Services\LabService;
use App\Support\JalaliDate;
use App\Support\Money;
use App\Support\Quantity;
use App\Support\SchoolCalendar;
use Carbon\CarbonImmutable;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;
use Illuminate\Validation\Rule;
use Illuminate\View\View;

/**
 * لابراتوارها، جدول هفته و گزارش (مرحله‌ی ۱۸).
 * تنظیم لابراتوارها و گزارش: labs.manage (مسیر)؛ جدول هفته: هر کسی که فعالیت‌ها را می‌بیند.
 */
class LabController extends Controller
{
    public function __construct(private readonly LabService $labs) {}

    public function setup(): View
    {
        return view('labs.setup', [
            'labs' => Lab::with(['room', 'manager'])->withCount(['items' => fn ($q) => $q->where('is_active', true)])->orderBy('name')->get(),
            'rooms' => Room::where('is_active', true)->orderByRaw('type = ? desc', [RoomType::Lab->value])->orderBy('name')->pluck('name', 'id')->all(),
            'staff' => Staff::query()->where('employment_status', '!=', 'left')->orderBy('first_name')->get()->mapWithKeys(fn ($s) => [$s->id => $s->full_name])->all(),
        ]);
    }

    public function store(Request $request): RedirectResponse
    {
        $this->labs->saveLab(null, $this->validated($request), $request->user());

        return back()->with('success', __('labs.lab_saved'));
    }

    public function update(Request $request, Lab $lab): RedirectResponse
    {
        $this->labs->saveLab($lab, $this->validated($request, $lab) + ['is_active' => $request->boolean('is_active')], $request->user());

        return back()->with('success', __('labs.lab_saved'));
    }

    /** جدول یک هفته: فعالیت‌های پلان‌شده و انجام‌شده هر روز (؟week=تاریخ، ؟lab=) */
    public function schedule(Request $request): View
    {
        Gate::authorize('viewAny', PracticalActivity::class);
        $date = JalaliDate::parse((string) $request->query('week')) ?? (preg_match('/^\d{4}-\d{2}-\d{2}$/', (string) $request->query('week')) ? CarbonImmutable::parse($request->query('week')) : AttendanceService::today());
        $start = $date->startOfDay()->subDays(($date->dayOfWeek + 1) % 7);   // شنبه
        $days = collect(range(0, 6))->map(fn ($i) => $start->addDays($i))
            ->filter(fn ($d) => in_array(strtolower($d->format('l')), SchoolCalendar::schoolDays(), true))->values();
        $lab = (int) $request->query('lab');

        $activities = PracticalActivity::query()->with(['lab', 'section.gradeLevel', 'subject', 'teacher', 'period'])
            ->whereBetween('activity_date', [$start->toDateString(), $start->addDays(6)->toDateString()])
            ->where('status', '!=', ActivityStatus::Cancelled->value)
            ->when($lab, fn ($q) => $q->where('lab_id', $lab))
            ->get()->sortBy(fn ($a) => [$a->period?->shift?->value ?? 'z', $a->period?->sort_order ?? 99])->groupBy(fn ($a) => $a->activity_date->toDateString());

        return view('labs.schedule', [
            'days' => $days,
            'start' => $start,
            'activities' => $activities,
            'labs' => Lab::active()->orderBy('name')->get(),
            'lab' => $lab,
            'canCreate' => Gate::allows('create', PracticalActivity::class),
        ]);
    }

    public function reports(Request $request): View
    {
        $year = AcademicYear::current();
        $from = $year?->start_date->toDateString() ?? now()->subYear()->toDateString();

        $perLab = Lab::orderBy('name')->get()->map(function (Lab $lab) use ($year) {
            $items = LabItem::where('lab_id', $lab->id)->where('is_active', true)->get();
            $value = $items->sum(fn ($i) => $i->unit_price !== null ? Money::toCents($i->unit_price) * Quantity::toHundredths($i->quantity) / 100 : 0);

            return [
                'lab' => $lab,
                'items' => $items->count(),
                'value' => (int) round($value),
                'low' => $items->filter->isLow()->count(),
                'expired' => $items->filter->isExpired()->count(),
                'damaged' => $items->filter(fn ($i) => Quantity::toHundredths($i->damaged_quantity) > 0)->count(),
                'done' => $year ? PracticalActivity::where('lab_id', $lab->id)->where('academic_year_id', $year->id)->where('status', ActivityStatus::Done->value)->count() : 0,
            ];
        });

        $bySubject = $year ? PracticalActivity::query()->with(['subject', 'section.gradeLevel'])
            ->where('academic_year_id', $year->id)->where('status', ActivityStatus::Done->value)
            ->select('section_id', 'subject_id', DB::raw('count(*) as n'))->groupBy('section_id', 'subject_id')->get()
            ->sortBy(fn ($r) => [$r->section->gradeLevel->sort_order, $r->section->name, $r->subject->name])->values() : collect();

        $consumption = LabItemEvent::query()->join('lab_items', 'lab_items.id', '=', 'lab_item_events.lab_item_id')
            ->where('lab_item_events.type', LabEventType::Used->value)->where('lab_item_events.event_date', '>=', $from)
            ->select('lab_item_events.lab_item_id', DB::raw('sum(lab_item_events.quantity) as total'), DB::raw('count(*) as times'))
            ->groupBy('lab_item_events.lab_item_id')->orderByDesc('times')->limit(15)->get();
        $consumedItems = LabItem::with('lab')->whereIn('id', $consumption->pluck('lab_item_id'))->get()->keyBy('id');

        return view('labs.reports', [
            'year' => $year,
            'perLab' => $perLab,
            'bySubject' => $bySubject,
            'consumption' => $consumption->map(fn ($r) => ['item' => $consumedItems->get($r->lab_item_id), 'total' => $r->total, 'times' => (int) $r->times])->filter(fn ($r) => $r['item']),
            'expired' => LabItem::with('lab')->where('is_active', true)->alert('expired')->orderBy('expires_on')->get(),
            'expiring' => LabItem::with('lab')->where('is_active', true)->alert('expiring')->orderBy('expires_on')->get(),
            'damaged' => LabItem::with('lab')->where('is_active', true)->alert('damaged')->orderBy('name')->get(),
            'low' => LabItem::with('lab')->where('is_active', true)->alert('low')->orderBy('name')->get(),
            'pending' => $year ? PracticalActivity::where('academic_year_id', $year->id)->where('status', ActivityStatus::Planned->value)->where('activity_date', '<', AttendanceService::today()->toDateString())->count() : 0,
            'print' => $request->boolean('print'),
        ]);
    }

    /** @return array<string, mixed> */
    private function validated(Request $request, ?Lab $lab = null): array
    {
        return $request->validate([
            'name' => ['required', 'string', 'max:100', Rule::unique('labs', 'name')->ignore($lab?->id)],
            'type' => ['required', Rule::enum(LabType::class)],
            'room_id' => ['nullable', 'integer', 'exists:rooms,id'],
            'staff_id' => ['nullable', 'integer', 'exists:staff,id'],
            'safety_notes' => ['nullable', 'string', 'max:2000'],
            'notes' => ['nullable', 'string', 'max:500'],
        ], [], __('labs.attributes'));
    }
}
