<?php

namespace App\Http\Controllers\Timetable;

use App\Http\Controllers\Controller;
use App\Models\AcademicYear;
use App\Models\Room;
use App\Models\Staff;
use App\Services\TimetableService;
use Illuminate\Http\Request;
use Illuminate\View\View;

/**
 * جدول هفته‌ی یک معلم یا یک اتاق (مرحله‌ی ۱۵).
 * معلم: timetable.manage هر معلم؛ معلمی که حسابش به پرونده‌ی کارمند وصل است فقط جدول خودش. اتاق: فقط timetable.manage.
 */
class WeeklyViewController extends Controller
{
    public function __construct(private readonly TimetableService $timetable) {}

    public function teachers(Request $request): View
    {
        $user = $request->user();
        $manage = TimetableController::canManage($user);
        abort_unless($manage || $user->staff_id, 403);

        $teachers = $manage ? TimetableController::teachers() : Staff::whereKey($user->staff_id)->get();
        $staff = $manage ? ($teachers->firstWhere('id', $request->integer('staff')) ?? ($request->integer('staff') ? abort(404) : $teachers->first())) : $teachers->first();

        return $this->week($request, 'teacher', $teachers, $staff, fn ($year) => $this->timetable->teacherGrid($staff, $year));
    }

    public function rooms(Request $request): View
    {
        abort_unless(TimetableController::canManage($request->user()), 403);
        $rooms = Room::where('is_active', true)->orderBy('name')->get();
        $room = $rooms->firstWhere('id', $request->integer('room')) ?? $rooms->first();

        return $this->week($request, 'room', $rooms, $room, fn ($year) => $this->timetable->roomGrid($room, $year));
    }

    private function week(Request $request, string $kind, $options, $current, callable $grid): View
    {
        $year = AcademicYear::current();
        $cells = $year && $current ? $grid($year) : [];

        return view('timetable.weekly', [
            'kind' => $kind,
            'year' => $year,
            'options' => $options,
            'current' => $current,
            'grid' => $cells,
            'periods' => $this->timetable->periodsFor($cells),
            'days' => $this->timetable->days(),
            'hours' => collect($cells)->flatten()->count(),
            'canManage' => TimetableController::canManage($request->user()),
            'print' => $request->boolean('print'),
        ]);
    }
}
