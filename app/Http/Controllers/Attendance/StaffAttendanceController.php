<?php

namespace App\Http\Controllers\Attendance;

use App\Enums\AttendanceStatus;
use App\Enums\EmploymentStatus;
use App\Http\Controllers\Attendance\Concerns\ResolvesDates;
use App\Http\Controllers\Controller;
use App\Models\AcademicYear;
use App\Models\AttendanceSession;
use App\Models\Staff;
use App\Models\StaffAttendance;
use App\Services\AttendanceService;
use App\Support\Csv;
use App\Support\Digits;
use App\Support\JalaliDate;
use App\Support\SchoolCalendar;
use Carbon\CarbonImmutable;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;
use Illuminate\Validation\Rule;
use Illuminate\View\View;
use Symfony\Component\HttpFoundation\StreamedResponse;

/** حاضری ساده‌ی معلمان و کارکنان (مجوز staff.manage): ورق روزانه و خلاصه‌ی ماهوار. */
class StaffAttendanceController extends Controller
{
    use ResolvesDates;

    public function __construct(private readonly AttendanceService $attendance) {}

    public function index(Request $request): View
    {
        Gate::authorize('manageStaff', AttendanceSession::class);

        $date = $this->dateFrom($request);
        $today = AttendanceService::today();
        $records = $this->attendance->staffRecords($date);

        return view('attendance.staff.index', [
            'date' => $date,
            'today' => $today,
            'staff' => $this->attendance->staffForDay($date),
            'records' => $records,
            'recorder' => $records->isNotEmpty() ? $records->sortByDesc('updated_at')->first()->recorder : null,
            'canRecord' => $date->lte($today),
            'isHoliday' => SchoolCalendar::isWeeklyHoliday($date),
        ]);
    }

    public function store(Request $request): RedirectResponse
    {
        Gate::authorize('manageStaff', AttendanceSession::class);

        $data = $request->validate([
            'date' => ['required', 'date_format:Y-m-d'],
            'status' => ['required', 'array'],
            'status.*' => ['required', Rule::enum(AttendanceStatus::class)],
            'note' => ['array'],
            'note.*' => ['nullable', 'string', 'max:255'],
        ], [], ['status' => __('students.status'), 'note.*' => __('attendance.note')]);

        $date = CarbonImmutable::createFromFormat('!Y-m-d', $data['date'], JalaliDate::TIMEZONE);
        if ($date->gt(AttendanceService::today())) {
            return back()->with('error', __('attendance.blocked_future'));
        }

        $result = $this->attendance->saveStaff($date, $data['status'], $data['note'] ?? [], $request->user());

        $message = match (true) {
            $result['created'] => __('attendance.staff_saved', [
                'present' => Digits::toPersian((string) ($result['counts']['present'] + $result['counts']['late'])),
                'absent' => Digits::toPersian((string) ($result['counts']['absent'] + $result['counts']['excused'] + $result['counts']['sick'])),
            ]),
            $result['changed'] > 0 => __('attendance.corrected', ['count' => Digits::toPersian((string) $result['changed'])]),
            default => __('attendance.no_changes'),
        };

        return redirect()->route('attendance.staff.index', ['date' => $date->format('Y-m-d')])->with('success', $message);
    }

    public function monthly(Request $request): View
    {
        Gate::authorize('manageStaff', AttendanceSession::class);
        $month = $this->monthFrom($request);

        return view('attendance.staff.monthly', [
            'month' => $month,
            'monthLabel' => JalaliDate::monthName($month['month']).' '.Digits::toPersian((string) $month['year']),
            'monthOptions' => $this->monthOptions(AcademicYear::current()),
            'staff' => $this->monthStaff($month),
            'totals' => $this->attendance->staffTotals($month['from'], $month['to']),
            'print' => $request->boolean('print'),
        ]);
    }

    public function monthlyExport(Request $request): StreamedResponse
    {
        Gate::authorize('manageStaff', AttendanceSession::class);
        $month = $this->monthFrom($request);
        $totals = $this->attendance->staffTotals($month['from'], $month['to']);
        $statuses = AttendanceStatus::cases();

        $rows = $this->monthStaff($month)->map(fn (Staff $s) => array_merge(
            [$s->full_name, $s->staff_type->label(), $s->position],
            array_map(fn ($st) => $totals[$s->id][$st->value] ?? 0, $statuses),
        ));

        return Csv::download("staff-attendance-{$month['key']}.csv", array_merge(
            [__('students.full_name'), __('staff.staff_type'), __('staff.position')],
            array_map(fn ($s) => $s->label(), $statuses),
        ), $rows);
    }

    /** کارکنان فعلی، به‌علاوه‌ی کسانی که در این ماه حاضری دارند */
    private function monthStaff(array $month)
    {
        $recorded = StaffAttendance::query()
            ->whereBetween('date', [$month['from']->format('Y-m-d'), $month['to']->format('Y-m-d')])
            ->distinct()
            ->pluck('staff_id')
            ->all();

        return Staff::query()
            ->withTrashed()
            ->where(fn ($q) => $q
                ->where(fn ($q) => $q->whereNull('deleted_at')->where('employment_status', '!=', EmploymentStatus::Left))
                ->orWhereIn('id', $recorded ?: [0]))
            ->orderBy('staff_type')
            ->orderBy('first_name')
            ->get();
    }
}
