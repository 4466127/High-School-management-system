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

        return $teachers->map(fn (Staff $t) => ['teacher' => $t] + $this->summary($this->assignments($t, $year), $t, $year))->values();
    }

    /** @return array{teacher: Staff, rows: list<array>}+array */
    public function detail(Staff $teacher, AcademicYear $year): array
    {
        $rows = $this->assignments($teacher, $year);

        return ['teacher' => $teacher, 'rows' => $rows] + $this->summary($rows, $teacher, $year);
    }

    /** هر مضمون و بخش معلم با ساعت هفته، تکمیل نمره و پلان */
    private function assignments(Staff $teacher, AcademicYear $year): Collection
    {
        $exams = $this->results->countedExams($year)->pluck('id')->all();
        $assignments = TeacherAssignment::query()->with(['section.gradeLevel', 'subject'])
            ->where('staff_id', $teacher->id)
            ->whereHas('section', fn ($q) => $q->where('academic_year_id', $year->id))
            ->get()
            ->sortBy(fn ($a) => [$a->section->gradeLevel->sort_order, $a->section->name, $a->subject->name])->values();

        $hours = TimetableEntry::where('academic_year_id', $year->id)->where('staff_id', $teacher->id)
            ->selectRaw('section_id, subject_id, count(*) n')->groupBy('section_id', 'subject_id')->get()
            ->mapWithKeys(fn ($r) => [$r->section_id.'|'.$r->subject_id => (int) $r->n]);
        $plans = TeachingPlan::with('items')->whereIn('section_id', $assignments->pluck('section_id')->unique()->all() ?: [0])->get()
            ->keyBy(fn ($p) => $p->section_id.'|'.$p->subject_id);

        return $assignments->map(function (TeacherAssignment $a) use ($exams, $hours, $plans) {
            $key = $a->section_id.'|'.$a->subject_id;
            $enrollments = Enrollment::where('section_id', $a->section_id)->whereIn('status', self::RESULT_STATUSES)->pluck('id')->all();
            $expected = count($enrollments) * count($exams);
            $entered = $expected > 0
                ? Mark::whereIn('enrollment_id', $enrollments)->whereIn('exam_id', $exams)->where('subject_id', $a->subject_id)
                    ->where(fn ($q) => $q->whereNotNull('mark')->orWhere('is_absent', true))->count()
                : 0;
            $plan = $plans->get($key);

            return [
                'section' => $a->section,
                'subject' => $a->subject,
                'hours' => $hours->get($key, 0),
                'marks_expected' => $expected,
                'marks_entered' => $entered,
                'marks_percent' => $expected > 0 ? (int) floor($entered * 100 / $expected) : null,
                'plan' => $plan,
                'progress' => $plan?->progress(),
            ];
        });
    }

    private function summary(Collection $rows, Staff $teacher, AcademicYear $year): array
    {
        $to = min(now()->toDateString(), $year->end_date->toDateString());
        $attendance = StaffAttendance::where('staff_id', $teacher->id)
            ->whereBetween('date', [$year->start_date->toDateString(), $to])
            ->selectRaw('status, count(*) n')->groupBy('status')->toBase()->pluck('n', 'status');
        $count = fn (AttendanceStatus ...$s) => (int) collect($s)->sum(fn ($x) => $attendance[$x->value] ?? 0);

        $expected = $rows->sum('marks_expected');
        $withPlan = $rows->filter(fn ($r) => $r['plan'] !== null);

        return [
            'subjects' => $rows->count(),
            'hours' => TimetableEntry::where('academic_year_id', $year->id)->where('staff_id', $teacher->id)->count(),
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
