<?php

namespace App\Http\Controllers\Attendance;

use App\Enums\AttendanceStatus;
use App\Http\Controllers\Attendance\Concerns\ResolvesDates;
use App\Http\Controllers\Controller;
use App\Models\AcademicYear;
use App\Models\AttendanceSession;
use App\Models\Enrollment;
use App\Models\Section;
use App\Models\Setting;
use App\Services\AttendanceService;
use App\Support\Csv;
use App\Support\Digits;
use App\Support\JalaliDate;
use App\Support\SchoolCalendar;
use Illuminate\Http\Request;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Gate;
use Illuminate\View\View;
use Symfony\Component\HttpFoundation\StreamedResponse;

/** گزارش ماهوار (جدول یک بخش یا خلاصه‌ی صنف‌ها) و فهرست غیابت زیاد؛ هر کدام با چاپ و CSV. */
class AttendanceReportController extends Controller
{
    use ResolvesDates;

    public function __construct(private readonly AttendanceService $attendance) {}

    public function monthly(Request $request): View
    {
        Gate::authorize('viewAny', AttendanceSession::class);

        $year = AcademicYear::current();
        $month = $this->monthFrom($request);
        $sections = $year ? $this->attendance->sections($request->user(), $year) : collect();
        $section = $this->chosenSection($request, $sections);

        $data = [
            'year' => $year,
            'month' => $month,
            'monthLabel' => JalaliDate::monthName($month['month']).' '.Digits::toPersian((string) $month['year']),
            'monthOptions' => $this->monthOptions($year),
            'sections' => $sections,
            'section' => $section,
            'print' => $request->boolean('print'),
        ];

        if ($section) {
            $grid = $this->attendance->monthGrid($section, $month['from'], $month['to']);
            $data += $grid + ['holidays' => collect($grid['days'])->filter(fn ($d) => SchoolCalendar::isWeeklyHoliday($d))->map->format('Y-m-d')->values()->all()];
        } else {
            $data['summary'] = $this->attendance->sectionSummary($sections->pluck('id')->all(), $month['from'], $month['to']);
        }

        return view('attendance.reports.monthly', $data);
    }

    public function monthlyExport(Request $request): StreamedResponse
    {
        Gate::authorize('viewAny', AttendanceSession::class);

        $year = AcademicYear::current();
        $month = $this->monthFrom($request);
        $sections = $year ? $this->attendance->sections($request->user(), $year) : collect();
        $section = $this->chosenSection($request, $sections);
        $statuses = AttendanceStatus::cases();

        if ($section) {
            $grid = $this->attendance->monthGrid($section, $month['from'], $month['to']);
            $header = array_merge(
                [__('academics.roll_no'), __('students.full_name'), __('students.father_name')],
                array_map(fn ($d) => (string) JalaliDate::fromGregorian($d)[2], $grid['days']),
                array_map(fn ($s) => $s->label(), $statuses),
            );
            $rows = $grid['rows']->map(fn ($row) => array_merge(
                [$row['enrollment']->roll_no, $row['enrollment']->student->full_name, $row['enrollment']->student->father_name],
                array_map(fn ($d) => isset($row['marks'][$d->format('Y-m-d')]) ? $row['marks'][$d->format('Y-m-d')]->code() : '', $grid['days']),
                array_map(fn ($s) => $row['totals'][$s->value], $statuses),
            ));

            return Csv::download("attendance-{$month['key']}-section-{$section->id}.csv", $header, $rows);
        }

        $summary = $this->attendance->sectionSummary($sections->pluck('id')->all(), $month['from'], $month['to']);
        $header = array_merge(
            [__('academics.section'), __('academics.students_label'), __('attendance.days_taken')],
            array_map(fn ($s) => $s->label(), $statuses),
            [__('attendance.percent')],
        );
        $rows = $sections->map(fn (Section $s) => array_merge(
            [$s->label, $s->active_enrollments_count, $summary[$s->id]['days']],
            array_map(fn ($st) => $summary[$s->id]['counts'][$st->value], $statuses),
            [AttendanceService::percent($summary[$s->id]['counts'])],
        ));

        return Csv::download("attendance-{$month['key']}-summary.csv", $header, $rows);
    }

    public function absentees(Request $request): View
    {
        Gate::authorize('viewAny', AttendanceSession::class);

        return view('attendance.reports.absentees', $this->absenteeData($request) + ['print' => $request->boolean('print')]);
    }

    public function absenteesExport(Request $request): StreamedResponse
    {
        Gate::authorize('viewAny', AttendanceSession::class);
        $data = $this->absenteeData($request);

        $rows = $data['rows']->map(fn ($row) => [
            $row['enrollment']->section->label,
            $row['enrollment']->roll_no,
            $row['enrollment']->student->full_name,
            $row['enrollment']->student->father_name,
            $row['totals']->absent,
            $row['totals']->excused,
            $row['totals']->sick,
            $row['totals']->late,
            $row['totals']->total,
            $row['deprived'] ? __('app.yes') : '',
        ]);

        return Csv::download('absentees.csv', [
            __('academics.section'), __('academics.roll_no'), __('students.full_name'), __('students.father_name'),
            AttendanceStatus::Absent->label(), AttendanceStatus::Excused->label(), AttendanceStatus::Sick->label(),
            AttendanceStatus::Late->label(), __('attendance.days_recorded'), __('attendance.deprived'),
        ], $rows);
    }

    private function absenteeData(Request $request): array
    {
        $year = AcademicYear::current();
        $sections = $year ? $this->attendance->sections($request->user(), $year) : collect();
        $section = $this->chosenSection($request, $sections);
        $default = max(1, (int) Setting::get('absence_alert_days', 10));
        $min = $request->filled('min') ? max(1, min(365, (int) Digits::toLatin((string) $request->query('min')))) : $default;
        $deprivation = (int) Setting::get('deprivation_absence_days', 0);

        $rows = collect();
        if ($year) {
            $ids = $section ? [$section->id] : $sections->pluck('id')->all();
            $totals = $this->attendance->yearTotals($year, $ids, $min);
            $enrollments = Enrollment::query()->with(['student', 'section.gradeLevel'])->whereIn('id', $totals->keys()->all() ?: [0])->get()->keyBy('id');
            $rows = $totals
                ->filter(fn ($t) => $enrollments->has($t->enrollment_id))
                ->map(fn ($t) => [
                    'enrollment' => $enrollments[$t->enrollment_id],
                    'totals' => $t,
                    'deprived' => $deprivation > 0 && $t->absent > $deprivation,
                ])
                ->sortByDesc(fn ($row) => [$row['totals']->absent, $row['totals']->excused + $row['totals']->sick])
                ->values();
        }

        return [
            'year' => $year,
            'sections' => $sections,
            'section' => $section,
            'min' => $min,
            'defaultMin' => $default,
            'deprivation' => $deprivation,
            'rows' => $rows,
        ];
    }

    /** بخش انتخاب‌شده فقط اگر در محدوده‌ی کاربر باشد */
    private function chosenSection(Request $request, Collection $sections): ?Section
    {
        $id = $request->integer('section');
        if (! $id) {
            return null;
        }
        $section = $sections->firstWhere('id', $id);
        abort_unless($section, 403);

        return $section;
    }
}
