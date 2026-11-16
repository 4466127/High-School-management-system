<?php

namespace App\Http\Controllers\Attendance;

use App\Enums\AttendanceStatus;
use App\Http\Controllers\Attendance\Concerns\ResolvesDates;
use App\Http\Controllers\Controller;
use App\Models\AcademicYear;
use App\Models\AttendanceSession;
use App\Models\Section;
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

/** حاضری روزانه: فهرست بخش‌ها در یک روز (گزارش روزانه)، ورق حاضری هر بخش، ذخیره و اصلاح. */
class DailyAttendanceController extends Controller
{
    use ResolvesDates;

    public function __construct(private readonly AttendanceService $attendance) {}

    public function index(Request $request): View
    {
        Gate::authorize('viewAny', AttendanceSession::class);

        $date = $this->dateFrom($request);
        $year = AcademicYear::current();
        $sections = $year ? $this->attendance->sections($request->user(), $year) : collect();
        $ids = $sections->pluck('id')->all();

        $sessions = AttendanceSession::query()
            ->with(['takenBy:id,name', 'updatedBy:id,name'])
            ->whereIn('section_id', $ids ?: [0])
            ->where('date', $date->format('Y-m-d'))
            ->get()
            ->keyBy('section_id');

        $counts = $this->attendance->dayCounts($ids, $date);
        $totals = array_fill_keys(array_column(AttendanceStatus::cases(), 'value'), 0);
        foreach ($counts as $sectionCounts) {
            foreach ($sectionCounts as $status => $n) {
                $totals[$status] += $n;
            }
        }

        return view('attendance.daily.index', [
            'date' => $date,
            'today' => AttendanceService::today(),
            'year' => $year,
            'inYear' => $this->inYear($year, $date),
            'isHoliday' => SchoolCalendar::isWeeklyHoliday($date),
            'sections' => $sections,
            'sessions' => $sessions,
            'counts' => $counts,
            'totals' => $totals,
            'nonPresent' => $this->attendance->dayNonPresent($ids, $date),
            'noTeacherLink' => $this->attendance->scopedSectionIds($request->user()) !== null && ! $request->user()->staff_id,
            'print' => $request->boolean('print'),
        ]);
    }

    /** CSV غیرحاضران، رخصت‌ها، مریض‌ها و تأخیرهای یک روز */
    public function export(Request $request): StreamedResponse
    {
        Gate::authorize('viewAny', AttendanceSession::class);

        $date = $this->dateFrom($request);
        $year = AcademicYear::current();
        $ids = $year ? $this->attendance->sections($request->user(), $year)->pluck('id')->all() : [];
        $jalali = Digits::toLatin(JalaliDate::format($date));

        $rows = $this->attendance->dayNonPresent($ids, $date)->map(fn ($r) => [
            $jalali,
            $r->session->section->shortLabel,
            $r->enrollment->roll_no,
            $r->enrollment->student->full_name,
            $r->enrollment->student->father_name,
            $r->status->label(),
            $r->note,
        ]);

        return Csv::download('attendance-'.$date->format('Y-m-d').'.csv', [
            __('academics.date'), __('academics.section'), __('academics.roll_no'), __('students.full_name'),
            __('students.father_name'), __('students.status'), __('attendance.note'),
        ], $rows);
    }

    /** ورق حاضری یک بخش در یک روز (ثبت، اصلاح یا فقط مشاهده) */
    public function show(Request $request, Section $section): View
    {
        Gate::authorize('viewSection', [AttendanceSession::class, $section]);
        $section->load(['gradeLevel', 'academicYear', 'classTeacher']);

        $date = $this->dateFrom($request);
        $session = $this->attendance->findSession($section, $date)?->load(['takenBy:id,name', 'updatedBy:id,name', 'records']);
        $blocked = $this->blockedReason($request, $section, $date);

        return view('attendance.daily.sheet', [
            'section' => $section,
            'date' => $date,
            'today' => AttendanceService::today(),
            'session' => $session,
            'records' => $session ? $session->records->keyBy('enrollment_id') : collect(),
            'enrollments' => $this->attendance->sheetEnrollments($section, $date, $session),
            'canRecord' => $blocked === null,
            'blocked' => $blocked,
            'isHoliday' => SchoolCalendar::isWeeklyHoliday($date),
        ]);
    }

    public function store(Request $request, Section $section): RedirectResponse
    {
        Gate::authorize('viewSection', [AttendanceSession::class, $section]);
        $section->load(['gradeLevel', 'academicYear']);

        $data = $request->validate([
            'date' => ['required', 'date_format:Y-m-d'],
            'status' => ['required', 'array'],
            'status.*' => ['required', Rule::enum(AttendanceStatus::class)],
            'note' => ['array'],
            'note.*' => ['nullable', 'string', 'max:255'],
        ], [], ['status' => __('students.status'), 'note.*' => __('attendance.note')]);

        $date = CarbonImmutable::createFromFormat('!Y-m-d', $data['date'], JalaliDate::TIMEZONE);
        Gate::authorize('record', [AttendanceSession::class, $section, $date]);
        if (! $this->inYear($section->academicYear, $date)) {
            return back()->with('error', __('attendance.blocked_out_of_year'));
        }

        $result = $this->attendance->saveSection($section, $date, $data['status'], $data['note'] ?? [], $request->user());

        $message = match (true) {
            $result['created'] => __('attendance.saved', [
                'section' => $section->shortLabel,
                'present' => Digits::toPersian((string) ($result['counts']['present'] + $result['counts']['late'])),
                'absent' => Digits::toPersian((string) ($result['counts']['absent'] + $result['counts']['excused'] + $result['counts']['sick'])),
            ]),
            $result['changed'] > 0 => __('attendance.corrected', ['count' => Digits::toPersian((string) $result['changed'])]),
            default => __('attendance.no_changes'),
        };

        return redirect()->route('attendance.daily.index', ['date' => $date->format('Y-m-d')])->with('success', $message);
    }

    /** چرا این کاربر نمی‌تواند حاضری این روز را ثبت کند؛ null = می‌تواند */
    private function blockedReason(Request $request, Section $section, CarbonImmutable $date): ?string
    {
        $today = AttendanceService::today();

        return match (true) {
            $date->gt($today) => __('attendance.blocked_future'),
            $section->academicYear->isClosed() => __('attendance.blocked_closed'),
            ! $this->inYear($section->academicYear, $date) => __('attendance.blocked_out_of_year'),
            Gate::allows('record', [AttendanceSession::class, $section, $date]) => null,
            $date->lt($today) && $request->user()->hasPermission('attendance.take') => __('attendance.blocked_past'),
            default => __('attendance.blocked_permission'),
        };
    }
}
