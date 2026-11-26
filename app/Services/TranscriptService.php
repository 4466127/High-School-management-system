<?php

namespace App\Services;

use App\Enums\EnrollmentStatus;
use App\Enums\FinalResult;
use App\Models\Enrollment;
use App\Models\GradeLevel;
use App\Models\Setting;
use App\Models\Student;
use App\Models\StudentPastResult;
use App\Support\PersianText;
use Illuminate\Support\Collection;

/**
 * سوانح تحصیلی چندساله / ترانسکریپت (مرحله‌ی ۱۴): سطرها مضامین، ستون‌ها صنف‌ها (پیش‌فرض سه صنف آخر: دهم، یازدهم، دوازدهم).
 * منبع هر ستون: ۱) نتیجه‌ی نهایی‌شده‌ی همین سیستم (final_snapshot)، ۲) نتیجه‌ی درج دستی (student_past_results)،
 * ۳) صنف جاری که هنوز نهایی نشده ← «در جریان». فقط خواندن.
 * شکل رسمی وزارت معارف حدس زده نشده؛ عنوان، صنف‌ها، امضاها و یادداشت پایین از «قالب کارنامه و ترانسکریپت» تنظیم می‌شود.
 */
class TranscriptService
{
    /** صنف‌های ترانسکریپت: از تنظیمات (transcript_grade_ids)، در غیر آن سه صنف آخر فعال */
    public function gradeLevels(): Collection
    {
        $ids = array_filter(array_map('intval', explode(',', (string) Setting::get('transcript_grade_ids', ''))));
        if ($ids !== []) {
            $levels = GradeLevel::query()->whereIn('id', $ids)->orderBy('sort_order')->get();
            if ($levels->isNotEmpty()) {
                return $levels;
            }
        }

        return GradeLevel::query()->where('is_active', true)->orderByDesc('sort_order')->limit(3)->get()->sortBy('sort_order')->values();
    }

    /**
     * @return array{columns: list<array>, rows: list<array{name: string, marks: list<?float>, passed: list<?bool>}>, total: ?float, average: ?float, complete: bool, result: ?string}
     */
    public function build(Student $student, ?Collection $levels = null): array
    {
        $levels ??= $this->gradeLevels();

        $enrollments = Enrollment::query()
            ->with(['academicYear', 'section'])
            ->where('student_id', $student->id)
            ->join('academic_years', 'academic_years.id', '=', 'enrollments.academic_year_id')
            ->join('sections', 'sections.id', '=', 'enrollments.section_id')
            ->whereIn('sections.grade_level_id', $levels->pluck('id')->all() ?: [0])
            ->orderByDesc('academic_years.start_date')
            ->select('enrollments.*', 'sections.grade_level_id as level_id')
            ->get();
        $past = StudentPastResult::query()->active()->where('student_id', $student->id)
            ->whereIn('grade_level_id', $levels->pluck('id')->all() ?: [0])
            ->orderByDesc('year_name')->orderByDesc('id')->get();

        $columns = $levels->map(function (GradeLevel $level) use ($enrollments, $past) {
            $mine = $enrollments->where('level_id', $level->id);
            $final = $mine->first(fn (Enrollment $e) => $e->finalized_at !== null && $e->final_result === FinalResult::Passed)
                ?? $mine->first(fn (Enrollment $e) => $e->finalized_at !== null);

            if ($final) {
                $snapshot = $final->final_snapshot ?? [];
                $grade = $final->final_grade ?? ResultCalculator::fromSnapshot($snapshot['settings'] ?? [])
                    ->grade($final->final_result, $final->final_average !== null ? (float) $final->final_average : null);

                return $this->column($level, 'system', (string) $final->academicYear->name, null,
                    collect($snapshot['subjects'] ?? [])->map(fn ($s) => ['name' => (string) $s['name'], 'mark' => $s['reexam'] ?? $s['final'], 'passed' => $s['passed']])->all(),
                    $final->final_total, $final->final_average, $final->final_result?->value, $grade);
            }

            $manual = $past->firstWhere('grade_level_id', $level->id);
            if ($manual) {
                return $this->column($level, 'manual', (string) $manual->year_name, $manual->school_name,
                    $manual->subjects ?? [], $manual->total, $manual->average, $manual->result?->value, $manual->grade_letter);
            }

            $current = $mine->first(fn (Enrollment $e) => in_array($e->status, [EnrollmentStatus::Active, EnrollmentStatus::Promoted, EnrollmentStatus::Repeated, EnrollmentStatus::Graduated], true));
            if ($current) {
                return $this->column($level, 'pending', (string) $current->academicYear->name, null, [], null, null, null, null);
            }

            return $this->column($level, 'missing', '', null, [], null, null, null, null);
        })->values();

        // سطرها: مضامین همه‌ی ستون‌ها به ترتیب اولین دیده‌شدن (نام یکسان‌شده با PersianText)
        $rows = [];
        foreach ($columns as $i => $column) {
            foreach ($column['subjects'] as $subject) {
                $key = PersianText::normalize($subject['name']);
                $rows[$key] ??= ['name' => $subject['name'], 'marks' => array_fill(0, $columns->count(), null), 'passed' => array_fill(0, $columns->count(), null)];
                $rows[$key]['marks'][$i] = $subject['mark'] !== null ? (float) $subject['mark'] : null;
                $rows[$key]['passed'][$i] = $subject['passed'];
            }
        }

        $done = $columns->filter(fn ($c) => $c['average'] !== null);
        $complete = $done->count() === $columns->count() && $columns->isNotEmpty();

        return [
            'columns' => $columns->all(),
            'rows' => array_values($rows),
            'total' => $done->isNotEmpty() ? round($done->sum('total'), 2) : null,
            'average' => $done->isNotEmpty() ? round($done->avg('average'), 2) : null,
            'complete' => $complete,
            'result' => $complete ? ($columns->every(fn ($c) => $c['result'] === FinalResult::Passed->value) ? FinalResult::Passed->value : FinalResult::Failed->value) : null,
        ];
    }

    private function column(GradeLevel $level, string $source, string $year, ?string $school, array $subjects, mixed $total, mixed $average, ?string $result, ?string $grade): array
    {
        return [
            'level' => $level,
            'source' => $source,
            'year' => $year,
            'school' => $school,
            'subjects' => array_values($subjects),
            'total' => $total !== null ? (float) $total : null,
            'average' => $average !== null ? (float) $average : null,
            'result' => $result,
            'grade' => $grade,
        ];
    }
}
