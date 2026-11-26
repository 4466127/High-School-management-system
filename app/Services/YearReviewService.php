<?php

namespace App\Services;

use App\Enums\EnrollmentStatus;
use App\Enums\ExamStatus;
use App\Enums\FinalResult;
use App\Models\AcademicYear;
use App\Models\Enrollment;
use App\Models\Mark;
use App\Models\Section;
use Illuminate\Support\Collection;

/**
 * مرور پایان سال (مرحله‌ی ۱۴): برای هر بخش سال جاری، گام‌های بستن سال یک‌جا دیده می‌شود —
 * ۱) نمره‌ها کامل ← ۲) نتیجه نهایی ← ۳) مشروطی (اگر مشروط دارد) نهایی ← ۴) ارتقا/تکرار/فارغ.
 * فقط خواندن؛ هر گام به صفحه‌ی خودش پیوند دارد.
 */
class YearReviewService
{
    public function __construct(private readonly ResultService $results) {}

    /**
     * @return array{year: AcademicYear, exams: Collection, next_year: ?AcademicYear, sections: list<array>, totals: array<string, int>}
     */
    public function review(AcademicYear $year): array
    {
        $exams = $this->results->countedExams($year);
        $sections = Section::query()->with('gradeLevel.subjects')->where('academic_year_id', $year->id)->ordered()->get();

        $rows = $sections->map(fn (Section $section) => $this->section($section, $exams))->all();

        $totals = ['students' => 0, 'passed' => 0, 'conditional' => 0, 'failed' => 0, 'deprived' => 0, 'incomplete' => 0, 'missing_marks' => 0, 'done' => 0];
        foreach ($rows as $row) {
            foreach (['students', 'passed', 'conditional', 'failed', 'deprived', 'incomplete', 'missing_marks'] as $key) {
                $totals[$key] += $row[$key];
            }
            $totals['done'] += $row['step'] === 'done' ? 1 : 0;
        }

        return [
            'year' => $year,
            'exams' => $exams,
            'open_exams' => $exams->filter(fn ($e) => $e->status !== ExamStatus::Locked)->values(),
            'next_year' => AcademicYear::query()->where('start_date', '>', $year->start_date)->orderBy('start_date')->first(),
            'sections' => $rows,
            'totals' => $totals,
        ];
    }

    private function section(Section $section, Collection $exams): array
    {
        $enrollments = $this->results->sectionEnrollments($section);
        $subjectIds = $section->gradeLevel->subjects->pluck('id')->all();
        $expected = $enrollments->count() * count($subjectIds) * $exams->count();
        $entered = $expected > 0
            ? Mark::query()->whereIn('enrollment_id', $enrollments->pluck('id')->all())->whereIn('exam_id', $exams->pluck('id')->all())
                ->whereIn('subject_id', $subjectIds)->where(fn ($q) => $q->whereNotNull('mark')->orWhere('is_absent', true))->count()
            : 0;

        $finalized = $enrollments->filter(fn (Enrollment $e) => $e->finalized_at !== null)->count();
        $counts = ['passed' => 0, 'conditional' => 0, 'failed' => 0, 'deprived' => 0, 'incomplete' => 0];
        if ($finalized > 0) {
            foreach ($enrollments as $e) {
                $counts[$e->final_result?->value ?? 'incomplete']++;
            }
        } elseif ($enrollments->isNotEmpty()) {
            foreach ($this->results->sectionResults($section)['rows'] as $row) {
                $counts[$row['result']['result']?->value ?? 'incomplete']++;
            }
        }

        // مشروطی: هنوز مشروط‌هایی که نتیجه‌ی مشروطی‌شان نهایی نشده
        $reexamPending = $enrollments->filter(fn (Enrollment $e) => $e->final_result === FinalResult::Conditional && $e->reexam_finalized_at === null)->count();
        $decided = $enrollments->filter(fn (Enrollment $e) => in_array($e->status, [EnrollmentStatus::Promoted, EnrollmentStatus::Repeated, EnrollmentStatus::Graduated], true))->count();

        $missing = max(0, $expected - $entered);
        $step = match (true) {
            $enrollments->isEmpty() => 'empty',
            $missing > 0 => 'marks',
            $finalized === 0 => 'finalize',
            $reexamPending > 0 => 'reexam',
            $decided < $enrollments->count() => 'promotion',
            default => 'done',
        };

        return [
            'section' => $section,
            'students' => $enrollments->count(),
            'missing_marks' => $missing,
            'marks_percent' => $expected > 0 ? (int) floor($entered * 100 / $expected) : 0,
            'finalized' => $finalized > 0,
            'reexam_pending' => $reexamPending,
            'decided' => $decided,
            'step' => $step,
        ] + $counts;
    }
}
