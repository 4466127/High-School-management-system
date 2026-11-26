<?php

namespace App\Http\Controllers\Timetable;

use App\Enums\RoomType;
use App\Enums\Shift;
use App\Http\Controllers\Controller;
use App\Models\Period;
use App\Models\Room;
use App\Services\ActivityLogger;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;
use Illuminate\View\View;

/**
 * ساعت‌های درسی و اتاق‌ها (مرحله‌ی ۱۵؛ timetable.manage). حذف نیست؛ «غیرفعال» از فهرست انتخاب برداشته می‌شود
 * و خانه‌های قبلی تقسیم اوقات دست نمی‌خورد. «ساعت‌های پیش‌فرض» شش ساعت ۴۰ دقیقه‌ای و یک تفریح می‌سازد.
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

    public function __construct(private readonly ActivityLogger $logger) {}

    public function index(): View
    {
        return view('timetable.setup', [
            'periods' => Period::query()->withCount('entries')->ordered()->get()->groupBy(fn ($p) => $p->shift->value),
            'rooms' => Room::query()->withCount('entries')->orderByDesc('is_active')->orderBy('name')->get(),
        ]);
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
