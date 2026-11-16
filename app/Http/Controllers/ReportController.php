<?php

namespace App\Http\Controllers;

use App\Enums\AttendanceStatus;
use App\Enums\Gender;
use App\Models\AcademicYear;
use App\Models\Setting;
use App\Services\AttendanceService;
use App\Services\ReportService;
use App\Services\ResultService;
use App\Support\Csv;
use App\Support\Digits;
use App\Support\JalaliDate;
use App\Support\Money;
use Carbon\CarbonImmutable;
use Illuminate\Http\Request;
use Illuminate\Support\Collection;
use Illuminate\View\View;
use Symfony\Component\HttpFoundation\StreamedResponse;

/**
 * گزارش‌ها (مرحله‌ی ۹): آمار و فهرست دانش‌آموزان، حاضری بخش‌ها، نتایج و ناکام‌ها (reports.academic؛ معلم فقط بخش‌های خودش)،
 * و خلاصه‌ی مالی (reports.finance؛ بخش مکتب با finance.view و کلید روشن، بخش کورس‌ها با courses.ledger). همه با چاپ و CSV.
 */
class ReportController extends Controller
{
    public function __construct(
        private readonly ReportService $reports,
        private readonly ResultService $results,
    ) {}

    // ------------------------------------------------------------------ دانش‌آموزان

    public function students(Request $request): View
    {
        [$year, $sections, $section, $all] = $this->scope($request);
        $gender = Gender::tryFrom((string) $request->query('gender'))?->value;
        $stats = $this->reports->studentStats($sections);

        return view('reports.students', [
            'year' => $year, 'sections' => $all, 'section' => $section, 'gender' => $gender, 'stats' => $stats,
            'byGrade' => $stats->groupBy(fn ($r) => $r['section']->gradeLevel->name)->map(fn ($rows) => [
                'male' => $rows->sum('male'), 'female' => $rows->sum('female'), 'total' => $rows->sum('total'), 'sections' => $rows->count(),
            ]),
            'list' => $request->boolean('list') ? $this->reports->studentList($sections, $gender) : null,
            'print' => $request->boolean('print'),
        ]);
    }

    public function studentsExport(Request $request): StreamedResponse
    {
        [, $sections] = $this->scope($request);
        $rows = $this->reports->studentList($sections, Gender::tryFrom((string) $request->query('gender'))?->value)->map(fn ($e) => [
            $e->section->gradeLevel->name, $e->section->name, $e->roll_no, $e->student->admission_no, $e->student->full_name,
            $e->student->father_name, $e->student->gender?->studentLabel(), $e->student->phone,
        ]);

        return Csv::download('students.csv', [
            __('academics.grade'), __('academics.section'), __('academics.roll_no'), __('students.admission_no'),
            __('students.full_name'), __('students.father_name'), __('reports.gender'), __('reports.phone'),
        ], $rows);
    }

    // ------------------------------------------------------------------ حاضری

    public function attendance(Request $request): View
    {
        [$year, $sections, $section, $all] = $this->scope($request);
        [$from, $to] = $this->range($request, $year);

        return view('reports.attendance', [
            'year' => $year, 'sections' => $all, 'section' => $section,
            'rows' => $this->reports->attendanceSummary($sections, $from, $to),
            'from' => $from, 'to' => $to, 'print' => $request->boolean('print'),
        ]);
    }

    public function attendanceExport(Request $request): StreamedResponse
    {
        [$year, $sections] = $this->scope($request);
        [$from, $to] = $this->range($request, $year);
        $statuses = AttendanceStatus::cases();
        $rows = $this->reports->attendanceSummary($sections, $from, $to)->map(fn ($r) => array_merge(
            [$r['section']->label, $r['days']],
            array_map(fn ($s) => $r['counts'][$s->value] ?? 0, $statuses),
            [$r['percent']],
        ));

        return Csv::download('attendance-summary.csv', array_merge(
            [__('academics.section'), __('reports.days')], array_map(fn ($s) => $s->label(), $statuses), [__('reports.attendance_percent')],
        ), $rows);
    }

    // ------------------------------------------------------------------ نتایج

    public function results(Request $request): View
    {
        [$year, $sections, $section, $all] = $this->scope($request);

        $summary = $this->reports->resultsSummary($sections);

        return view('reports.results', [
            'year' => $year, 'sections' => $all, 'section' => $section, 'print' => $request->boolean('print'),
            'rows' => $summary['sections'], 'students' => $summary['students'],
        ]);
    }

    public function resultsExport(Request $request): StreamedResponse
    {
        [, $sections] = $this->scope($request);
        $data = $this->reports->resultsSummary($sections);
        $rows = $data['students']->map(fn ($s) => [
            $s['section']->label, $s['enrollment']->roll_no, $s['enrollment']->student->full_name, $s['enrollment']->student->father_name,
            $s['result']->label(), $s['average'], implode('، ', $s['failed_subjects']), $s['provisional'] ? __('exams.provisional') : '',
        ]);

        return Csv::download('results-failed.csv', [
            __('academics.section'), __('academics.roll_no'), __('students.full_name'), __('students.father_name'),
            __('exams.result'), __('exams.average'), __('reports.failed_subjects'), __('reports.note'),
        ], $rows);
    }

    // ------------------------------------------------------------------ مالی

    public function finance(Request $request): View
    {
        return view('reports.finance', $this->financeData($request) + ['print' => $request->boolean('print')]);
    }

    public function financeExport(Request $request): StreamedResponse
    {
        $data = $this->financeData($request);
        $m = fn (int $cents) => Money::plain(Money::fromCents($cents));
        $rows = [];
        if ($s = $data['summary']['school']) {
            if ($data['feesEnabled']) {
                $rows[] = [__('reports.school_part'), __('finance.sum_fees'), $m($s['fees'])];
            }
            $rows[] = [__('reports.school_part'), __('finance.sum_income'), $m($s['income'])];
            $rows[] = [__('reports.school_part'), __('finance.sum_expense'), $m($s['expense'])];
            $rows[] = [__('reports.school_part'), __('finance.sum_balance'), $m($s['balance'])];
            if ($data['feesEnabled']) {
                $rows[] = [__('reports.school_part'), __('reports.debt_total'), $m($s['debt'])];
            }
        }
        if ($c = $data['summary']['courses']) {
            $rows[] = [__('reports.courses_part'), __('courses.sum_donations_cashbox'), $m($c['donations_cashbox'])];
            $rows[] = [__('reports.courses_part'), __('courses.sum_donations_direct'), $m($c['donations_direct'])];
            $rows[] = [__('reports.courses_part'), __('courses.sum_salaries'), $m($c['salaries'])];
            $rows[] = [__('reports.courses_part'), __('courses.sum_expenses'), $m($c['expenses'])];
            $rows[] = [__('reports.courses_part'), __('reports.course_cash_now'), $m($c['cash_balance'])];
        }

        return Csv::download('finance-summary.csv', [__('reports.part'), __('reports.item'), __('finance.amount')], $rows);
    }

    private function financeData(Request $request): array
    {
        $user = $request->user();
        abort_unless($user->hasPermission('reports.finance'), 403);
        $year = AcademicYear::current();
        [$from, $to] = $this->range($request, $year, wholeYear: true);
        $school = $user->hasPermission('finance.view') && (Setting::enabled('fees_enabled') || Setting::enabled('school_ledger_enabled'));
        $courses = $user->hasPermission('courses.ledger');

        return [
            'year' => $year, 'from' => $from, 'to' => $to,
            'feesEnabled' => Setting::enabled('fees_enabled'),
            'summary' => $this->reports->financeSummary($from->toDateString(), $to->toDateString(), $school, $courses, $year),
        ];
    }

    // ------------------------------------------------------------------ کمکی

    /**
     * سال جاری و بخش‌های در محدوده‌ی کاربر (اختیاری: فقط یک بخش).
     *
     * @return array{0: ?AcademicYear, 1: Collection, 2: mixed, 3: Collection}
     */
    private function scope(Request $request): array
    {
        $user = $request->user();
        abort_unless($user->hasPermission('reports.academic'), 403);
        $year = AcademicYear::current();
        $all = $year ? $this->results->visibleSections($user, $year) : collect();
        $section = $request->integer('section') ? $all->firstWhere('id', $request->integer('section')) : null;
        abort_if($request->integer('section') && ! $section, 403);

        return [$year, $section ? collect([$section]) : $all, $section, $all];
    }

    /** بازه‌ی تاریخ از from/to شمسی؛ پیش‌فرض: ماه جاری (یا از شروع سال تحصیلی تا امروز) */
    private function range(Request $request, ?AcademicYear $year, bool $wholeYear = false): array
    {
        $today = AttendanceService::today();
        $from = JalaliDate::parse(Digits::toLatin((string) $request->query('from')));
        $to = JalaliDate::parse(Digits::toLatin((string) $request->query('to')));

        if (! $from) {
            $from = $wholeYear && $year
                ? CarbonImmutable::parse($year->start_date->format('Y-m-d'), JalaliDate::TIMEZONE)
                : JalaliDate::monthRange(...array_slice(JalaliDate::fromGregorian($today), 0, 2))[0];
        }
        $to ??= $today;
        if ($to->lt($from)) {
            [$from, $to] = [$to, $from];
        }

        return [CarbonImmutable::parse($from->format('Y-m-d'), JalaliDate::TIMEZONE), CarbonImmutable::parse($to->format('Y-m-d'), JalaliDate::TIMEZONE)];
    }
}
