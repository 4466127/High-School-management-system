<?php

namespace App\Services;

use App\Enums\AttendanceStatus;
use App\Enums\EmploymentStatus;
use App\Enums\EnrollmentStatus;
use App\Enums\StaffType;
use App\Models\AcademicYear;
use App\Models\Enrollment;
use App\Models\Mark;
use App\Models\Staff;
use App\Models\StaffAttendance;
use App\Models\TeacherAssignment;
use App\Models\TeachingPlan;
use App\Models\TimetableEntry;
use Illuminate\Support\Collection;

/**
 * گزارش کار معلمان (مرحله‌ی ۱۶) — فقط خواندن، از داده‌ای که سیستم دارد؛ نمره‌ی «ارزیابی» ساخته نمی‌شود.
 * برای هر معلم: مضامین و بخش‌ها، ساعت هفته (تقسیم اوقات)، حاضری کارمند در سال، تکمیل نمره‌ها در امتحان‌های حساب‌شونده،
 * و پیشرفت پلان تدریس (درصد، موضوع‌های عقب‌مانده).
 */
class TeacherReportService
{
    private const RESULT_STATUSES = [EnrollmentStatus::Active, EnrollmentStatus::Promoted, EnrollmentStatus::Repeated, EnrollmentStatus::Graduated];

    public function __construct(private readonly ResultService $results) {}

    /** @return Collection<int, array> یک سطر برای هر معلم فعال */
    public function overview(AcademicYear $year): Collection
    {
        $teachers = Staff::query()
            ->whereIn('staff_type', [StaffType::Teacher, StaffType::CourseTeacher])
            ->where('employment_status', EmploymentStatus::Active)
            ->orderBy('first_name')->get();
        $ctx = $this->context($year, $teachers->pluck('id')->all());

        return $teachers->map(fn (Staff $t) => ['teacher' => $t] + $this->summary($this->assignments($t, $ctx), $t, $ctx))->values();
    }

    /** @return array{teacher: Staff, rows: list<array>}+array */
    public function detail(Staff $teacher, AcademicYear $year): array
    {
        $ctx = $this->context($year, [$teacher->id]);
        $rows = $this->assignments($teacher, $ctx);

        return ['teacher' => $teacher, 'rows' => $rows] + $this->summary($rows, $teacher, $ctx);
    }

    /**
     * همه‌ی داده‌ی لازم برای این معلمان با چند query گروهی (نه چند query برای هر معلم و هر مضمون) — مرحله‌ی ۱۹.
     *
     * @param  list<int>  $staffIds
     */
    private function context(AcademicYear $year, array $staffIds): array
    {
        $staffIds = $staffIds ?: [0];
        $exams = $this->results->countedExams($year)->pluck('id')->all();
        $assignments = TeacherAssignment::query()->with(['section.gradeLevel', 'subject'])
            ->whereIn('staff_id', $staffIds)
            ->whereHas('section', fn ($q) => $q->where('academic_year_id', $year->id))
            ->get()->groupBy('staff_id');
        $sectionIds = $assignments->flatten()->pluck('section_id')->unique()->values()->all() ?: [0];

        $students = Enrollment::whereIn('section_id', $sectionIds)->whereIn('status', self::RESULT_STATUSES)
            ->selectRaw('section_id, count(*) n')->groupBy('section_id')->toBase()->pluck('n', 'section_id');
        $marks = $exams === [] ? collect() : Mark::query()
            ->join('enrollments', 'enrollments.id', '=', 'marks.enrollment_id')
            ->whereIn('enrollments.section_id', $sectionIds)->whereIn('enrollments.status', self::RESULT_STATUSES)
            ->whereIn('marks.exam_id', $exams)
            ->where(fn ($q) => $q->whereNotNull('marks.mark')->orWhere('marks.is_absent', true))
            ->selectRaw('enrollments.section_id as section_id, marks.subject_id as subject_id, count(*) as n')
            ->groupBy('enrollments.section_id', 'marks.subject_id')->toBase()->get()
            ->mapWithKeys(fn ($r) => [$r->section_id.'|'.$r->subject_id => (int) $r->n]);
        $hours = TimetableEntry::where('academic_year_id', $year->id)->whereIn('staff_id', $staffIds)
            ->selectRaw('staff_id, section_id, subject_id, count(*) n')->groupBy('staff_id', 'section_id', 'subject_id')->toBase()->get();
        $plans = TeachingPlan::with('items')->whereIn('section_id', $sectionIds)->get()
            ->keyBy(fn ($p) => $p->section_id.'|'.$p->subject_id);
        $to = min(now()->toDateString(), $year->end_date->toDateString());
        $attendance = StaffAttendance::whereIn('staff_id', $staffIds)
            ->whereBetween('date', [$year->start_date->toDateString(), $to])
            ->selectRaw('staff_id, status, count(*) n')->groupBy('staff_id', 'status')->toBase()->get()
            ->groupBy('staff_id')->map(fn ($g) => $g->pluck('n', 'status'));

        return [
            'exam_count' => count($exams),
            'assignments' => $assignments,
            'students' => $students,
            'marks' => $marks,
            'hours' => $hours->mapWithKeys(fn ($r) => [$r->staff_id.'|'.$r->section_id.'|'.$r->subject_id => (int) $r->n]),
            'hours_total' => $hours->groupBy('staff_id')->map(fn ($g) => (int) $g->sum('n')),
            'plans' => $plans,
            'attendance' => $attendance,
        ];
    }

    /** هر مضمون و بخش معلم با ساعت هفته، تکمیل نمره و پلان */
    private function assignments(Staff $teacher, array $ctx): Collection
    {
        return collect($ctx['assignments']->get($teacher->id, []))
            ->sortBy(fn ($a) => [$a->section->gradeLevel->sort_order, $a->section->name, $a->subject->name])->values()
            ->map(function (TeacherAssignment $a) use ($teacher, $ctx) {
                $key = $a->section_id.'|'.$a->subject_id;
                $expected = (int) ($ctx['students'][$a->section_id] ?? 0) * $ctx['exam_count'];
                $entered = $expected > 0 ? (int) ($ctx['marks'][$key] ?? 0) : 0;
                $plan = $ctx['plans']->get($key);

                return [
                    'section' => $a->section,
                    'subject' => $a->subject,
                    'hours' => $ctx['hours']->get($teacher->id.'|'.$key, 0),
                    'marks_expected' => $expected,
                    'marks_entered' => $entered,
                    'marks_percent' => $expected > 0 ? (int) floor($entered * 100 / $expected) : null,
                    'plan' => $plan,
                    'progress' => $plan?->progress(),
                ];
            });
    }

    private function summary(Collection $rows, Staff $teacher, array $ctx): array
    {
        $attendance = $ctx['attendance']->get($teacher->id, collect());
        $count = fn (AttendanceStatus ...$s) => (int) collect($s)->sum(fn ($x) => $attendance[$x->value] ?? 0);

        $expected = $rows->sum('marks_expected');
        $withPlan = $rows->filter(fn ($r) => $r['plan'] !== null);

        return [
            'subjects' => $rows->count(),
            'hours' => (int) ($ctx['hours_total'][$teacher->id] ?? 0),
            'present' => $count(AttendanceStatus::Present, AttendanceStatus::Late),
            'late' => $count(AttendanceStatus::Late),
            'absent' => $count(AttendanceStatus::Absent),
            'leave' => $count(AttendanceStatus::Excused, AttendanceStatus::Sick),
            'marks_percent' => $expected > 0 ? (int) floor($rows->sum('marks_entered') * 100 / $expected) : null,
            'plans' => $withPlan->count(),
            'plans_approved' => $withPlan->filter(fn ($r) => $r['plan']->status->value === 'approved')->count(),
            'plan_percent' => $withPlan->isNotEmpty() ? (int) floor($withPlan->avg(fn ($r) => $r['progress']['percent'])) : null,
            'overdue' => (int) $withPlan->sum(fn ($r) => $r['progress']['overdue']),
        ];
    }
}
