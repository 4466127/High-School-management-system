<?php

namespace App\Http\Controllers\Timetable;

use App\Enums\RoomType;
use App\Enums\Shift;
use App\Http\Controllers\Controller;
use App\Models\AcademicYear;
use App\Models\GradeLevel;
use App\Models\Period;
use App\Models\Room;
use App\Models\Section;
use App\Services\ActivityLogger;
use App\Services\TimetableService;
use App\Support\Digits;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;
use Illuminate\View\View;

/**
 * ساعت‌های درسی و اتاق‌ها (مرحله‌ی ۱۵؛ timetable.manage). حذف نیست؛ «غیرفعال» از فهرست انتخاب برداشته می‌شود
 * و خانه‌های قبلی تقسیم اوقات دست نمی‌خورد. «تنظیم وقت نوبت» ساعت‌های هر نوبت را از روی وقت شروع، طول ساعت
 * و تفریح می‌سازد یا به‌روز می‌کند؛ «نوبت صنف‌ها» قبل یا بعد از ظهر بودن بخش‌های هر صنف را تعیین می‌کند.
 */
class SetupController extends Controller
{
    /** ساعت‌های پیش‌فرض هر نوبت: [نام، شروع، ختم، تفریح] */
    public const DEFAULTS = [
        'morning' => [['ساعت اول', '08:00', '08:40'], ['ساعت دوم', '08:40', '09:20'], ['ساعت سوم', '09:20', '10:00'],
            ['تفریح', '10:00', '10:15', true], ['ساعت چهارم', '10:15', '10:55'], ['ساعت پنجم', '10:55', '11:35'], ['ساعت ششم', '11:35', '12:15']],
        'afternoon' => [['ساعت اول', '13:00', '13:40'], ['ساعت دوم', '13:40', '14:20'], ['ساعت سوم', '14:20', '15:00'],
            ['تفریح', '15:00', '15:15', true], ['ساعت چهارم', '15:15', '15:55'], ['ساعت پنجم', '15:55', '16:35'], ['ساعت ششم', '16:35', '17:15']],
    ];

    /** تنظیم سریع پیش‌فرض: مکاتب افغانستان معمولاً صنف ۱ تا ۶ قبل از ظهر (۸ تا حدود ۱۲) و ۷ تا ۱۲ بعد از ظهر (۱ تا ۴ یا ۵) */
    public const PLAN_DEFAULTS = [
        'morning' => ['start_time' => '08:00', 'minutes' => 35, 'lessons' => 6, 'break_after' => 3, 'break_minutes' => 15],
        'afternoon' => ['start_time' => '13:00', 'minutes' => 35, 'lessons' => 6, 'break_after' => 3, 'break_minutes' => 15],
    ];

    /** صنف‌های اول تا ششم قبل از ظهر، بقیه بعد از ظهر (sort_order صنف) */
    public const MORNING_UP_TO_GRADE = 6;

    public function __construct(private readonly ActivityLogger $logger, private readonly TimetableService $timetable) {}

    public function index(): View
    {
        $periods = Period::query()->withCount('entries')->ordered()->get()->groupBy(fn ($p) => $p->shift->value);
        $year = AcademicYear::current();
        $sections = $year ? Section::with('gradeLevel')->where('academic_year_id', $year->id)->ordered()->get() : collect();

        return view('timetable.setup', [
            'periods' => $periods,
            'plans' => collect(Shift::cases())->mapWithKeys(fn (Shift $s) => [$s->value => $this->currentPlan($periods->get($s->value)) ?? self::PLAN_DEFAULTS[$s->value]]),
            'rooms' => Room::query()->withCount('entries')->orderByDesc('is_active')->orderBy('name')->get(),
            'year' => $year,
            'grades' => $sections->groupBy('grade_level_id')->map(fn ($list) => ['grade' => $list->first()->gradeLevel, 'sections' => $list])
                ->sortBy(fn ($g) => $g['grade']->sort_order)->values(),
        ]);
    }

    /** تنظیم سریع وقت یک نوبت: شروع، طول ساعت، شمار ساعت‌ها، تفریح بعد از کدام ساعت و چند دقیقه */
    public function generate(Request $request): RedirectResponse
    {
        $data = $request->validate([
            'shift' => ['required', Rule::enum(Shift::class)],
            'start_time' => ['required', 'date_format:H:i'],
            'minutes' => ['required', 'integer', 'min:20', 'max:90'],
            'lessons' => ['required', 'integer', 'min:1', 'max:10'],
            'break_after' => ['required', 'integer', 'min:0', 'max:10'],
            'break_minutes' => ['required', 'integer', 'min:0', 'max:60'],
        ], [], __('timetable.attributes'));
        $plan = TimetableService::periodPlan($data['start_time'], (int) $data['minutes'], (int) $data['lessons'], (int) $data['break_after'], (int) $data['break_minutes']);
        if (end($plan)['end'] < $data['start_time']) {
            throw ValidationException::withMessages(['lessons' => __('timetable.plan_past_midnight')]);
        }
        $shift = Shift::from($data['shift']);
        $this->timetable->applyPeriodPlan($shift, $plan, $request->user());

        return back()->with('success', __('timetable.plan_saved', ['shift' => $shift->label(), 'start' => Digits::toPersian($plan[0]['start']), 'end' => Digits::toPersian(end($plan)['end'])]));
    }

    /** نوبت بخش‌های هر صنف در سال جاری؛ preset=common ← صنف ۱ تا ۶ قبل از ظهر، ۷ تا ۱۲ بعد از ظهر */
    public function shifts(Request $request): RedirectResponse
    {
        $year = AcademicYear::current();
        abort_unless((bool) $year, 404);
        if ($request->input('preset') === 'common') {
            $shifts = GradeLevel::whereIn('id', Section::where('academic_year_id', $year->id)->select('grade_level_id'))->get()
                ->mapWithKeys(fn (GradeLevel $g) => [$g->id => $g->sort_order <= self::MORNING_UP_TO_GRADE ? Shift::Morning : Shift::Afternoon])->all();
        } else {
            $data = $request->validate(['shifts' => ['required', 'array'], 'shifts.*' => ['required', Rule::enum(Shift::class)]], [], __('timetable.attributes'));
            $shifts = collect($data['shifts'])->mapWithKeys(fn ($v, $k) => [(int) $k => Shift::from($v)])->all();
        }
        $result = $this->timetable->setGradeShifts($year, $shifts, $request->user());
        $message = __('timetable.shifts_saved', ['count' => Digits::toPersian((string) $result['changed'])]);
        if ($result['skipped'] !== []) {
            return back()->with('warning', $message.' '.__('timetable.shifts_skipped', ['sections' => implode('، ', $result['skipped'])]));
        }

        return back()->with('success', $message);
    }

    /** برنامه‌ی فعلی یک نوبت برای پر کردن فرم تنظیم سریع (از ساعت‌های فعال موجود) */
    private function currentPlan(?Collection $periods): ?array
    {
        $active = $periods?->where('is_active', true)->values();
        $lessons = $active?->where('is_break', false)->values();
        if (! $lessons || $lessons->isEmpty()) {
            return null;
        }
        $minutes = fn (Period $p) => (int) round((strtotime((string) $p->end_time) - strtotime((string) $p->start_time)) / 60);
        $firstBreak = $active->search(fn ($p) => $p->is_break);

        return [
            'start_time' => substr((string) $active->first()->start_time, 0, 5),
            'minutes' => $minutes($lessons->first()),
            'lessons' => $lessons->count(),
            'break_after' => $firstBreak === false ? 0 : $active->take($firstBreak)->where('is_break', false)->count(),
            'break_minutes' => $firstBreak === false ? 0 : $minutes($active->get($firstBreak)),
        ];
    }

    public function storePeriod(Request $request): RedirectResponse
    {
        $data = $this->validatePeriod($request);
        DB::transaction(function () use ($data) {
            $period = Period::create($data);
            $this->logger->log('timetable.period_created', $period, $period->name.' ('.$period->shift->label().')', new: $data);
        });

        return back()->with('success', __('timetable.period_saved'));
    }

    public function updatePeriod(Request $request, Period $period): RedirectResponse
    {
        $data = $this->validatePeriod($request, $period);
        DB::transaction(function () use ($period, $data) {
            $old = $period->only(array_keys($data));
            $period->update($data);
            $this->logger->log('timetable.period_updated', $period, $period->name.' ('.$period->shift->label().')', $old, $data);
        });

        return back()->with('success', __('timetable.period_saved'));
    }

    public function defaults(Request $request): RedirectResponse
    {
        $shift = Shift::from($request->validate(['shift' => ['required', Rule::enum(Shift::class)]])['shift']);
        if (Period::where('shift', $shift)->exists()) {
            return back()->with('warning', __('timetable.defaults_exist'));
        }
        DB::transaction(function () use ($shift) {
            foreach (self::DEFAULTS[$shift->value] as $i => $p) {
                Period::create(['name' => $p[0], 'shift' => $shift, 'start_time' => $p[1], 'end_time' => $p[2], 'sort_order' => $i + 1, 'is_break' => $p[3] ?? false]);
            }
            $this->logger->log('timetable.period_created', null, __('timetable.defaults').' — '.$shift->label(), new: ['count' => count(self::DEFAULTS[$shift->value])]);
        });

        return back()->with('success', __('timetable.defaults_created'));
    }

    public function storeRoom(Request $request): RedirectResponse
    {
        $data = $this->validateRoom($request);
        DB::transaction(function () use ($data) {
            $room = Room::create($data);
            $this->logger->log('timetable.room_created', $room, $room->name, new: $data);
        });

        return back()->with('success', __('timetable.room_saved'));
    }

    public function updateRoom(Request $request, Room $room): RedirectResponse
    {
        $data = $this->validateRoom($request, $room);
        DB::transaction(function () use ($room, $data) {
            $old = $room->only(array_keys($data));
            $room->update($data);
            $this->logger->log('timetable.room_updated', $room, $room->name, $old, $data);
        });

        return back()->with('success', __('timetable.room_saved'));
    }

    private function validatePeriod(Request $request, ?Period $period = null): array
    {
        $request->merge(['is_break' => $request->boolean('is_break'), 'is_active' => $period ? $request->boolean('is_active') : true]);

        return $request->validate([
            'name' => ['required', 'string', 'max:50'],
            'shift' => ['required', Rule::enum(Shift::class)],
            'start_time' => ['required', 'date_format:H:i'],
            'end_time' => ['required', 'date_format:H:i', 'after:start_time'],
            'sort_order' => ['required', 'integer', 'min:0', 'max:50'],
            'is_break' => ['boolean'],
            'is_active' => ['boolean'],
        ], [], __('timetable.attributes'));
    }

    private function validateRoom(Request $request, ?Room $room = null): array
    {
        $request->merge(['is_active' => $room ? $request->boolean('is_active') : true]);

        return $request->validate([
            'name' => ['required', 'string', 'max:100', Rule::unique('rooms', 'name')->ignore($room)],
            'type' => ['required', Rule::enum(RoomType::class)],
            'capacity' => ['nullable', 'integer', 'min:1', 'max:1000'],
            'notes' => ['nullable', 'string', 'max:300'],
            'is_active' => ['boolean'],
        ], [], __('timetable.attributes'));
    }
}
