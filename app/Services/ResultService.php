<?php

namespace App\Services;

use App\Enums\EnrollmentStatus;
use App\Enums\ExamStatus;
use App\Enums\FinalResult;
use App\Models\AcademicYear;
use App\Models\Enrollment;
use App\Models\Exam;
use App\Models\Mark;
use App\Models\Section;
use App\Models\User;
use App\Support\Digits;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

/**
 * جدول نتایج، نهایی‌کردن نتایج سال و داده‌ی کارنامه.
 * نتیجه‌ی نهایی با «نهایی‌کردن» در enrollments ذخیره می‌شود (همراه نسخه‌ی ثابت final_snapshot)،
 * پس تغییر بعدی تنظیمات یا نمره‌ی کامل امتحان، کارنامه‌ی سال بسته را عوض نمی‌کند.
 */
class ResultService
{
    public function __construct(
        private readonly AttendanceService $attendance,
        private readonly ActivityLogger $logger,
    ) {}

    /** بخش‌هایی که کاربر نتایج و کارنامه‌ی آن‌ها را می‌بیند (مدیر و اداره همه؛ معلم بخش‌های خودش) */
    public function visibleSections(User $user, AcademicYear $year): Collection
    {
        $all = $user->hasPermission('exams.manage') || $user->hasPermission('students.view_all');

        return Section::query()
            ->with(['gradeLevel', 'classTeacher'])
            ->withCount('activeEnrollments')
            ->where('academic_year_id', $year->id)
            ->when(! $all, fn ($q) => $q->whereIn('sections.id', $user->taughtSectionIds() ?: [0]))
            ->ordered()
            ->get();
    }

    /** امتحان‌های سال که در نتیجه‌ی نهایی حساب می‌شوند */
    public function countedExams(AcademicYear|int $year): Collection
    {
        return Exam::query()
            ->where('academic_year_id', $year instanceof AcademicYear ? $year->id : $year)
            ->where('counts_in_final', true)
            ->ordered()
            ->get();
    }

    /** شاملان بخش که نتیجه دارند (رفته‌ها نه) */
    public function sectionEnrollments(Section $section): Collection
    {
        return Enrollment::query()
            ->with('student')
            ->where('section_id', $section->id)
            ->whereIn('status', [EnrollmentStatus::Active, EnrollmentStatus::Promoted, EnrollmentStatus::Repeated, EnrollmentStatus::Graduated])
            ->orderByRaw('roll_no IS NULL, roll_no')
            ->orderBy('id')
            ->get();
    }

    /**
     * نتیجه‌ی زنده‌ی همه‌ی دانش‌آموزان یک بخش (از نمره‌های فعلی و تنظیمات فعلی).
     *
     * @return array{exams: Collection, subjects: Collection, rows: Collection, calculator: ResultCalculator}
     *                                                                                                        rows: ['enrollment', 'marks' => [subject_id][exam_id] => Mark, 'result' => array, 'rank' => ?int, 'attendance' => object|null]
     */
    public function sectionResults(Section $section, ?ResultCalculator $calculator = null): array
    {
        $calculator ??= ResultCalculator::fromSettings();
        $section->loadMissing(['gradeLevel.subjects', 'academicYear']);

        $exams = $this->countedExams($section->academic_year_id);
        $subjects = $section->gradeLevel->subjects;
        $enrollments = $this->sectionEnrollments($section);
        $marks = $this->marksFor($enrollments->pluck('id')->all(), $exams->pluck('id')->all());
        $attendance = $this->attendance->yearTotals($section->academicYear, [$section->id]);

        $examInput = $exams->map(fn (Exam $e) => ['id' => $e->id, 'max_mark' => (float) $e->max_mark, 'weight' => (float) $e->weight])->all();
        $subjectIds = $subjects->pluck('id')->all();

        $rows = $enrollments->map(function (Enrollment $enrollment) use ($marks, $attendance, $calculator, $examInput, $subjectIds) {
            $own = $marks->get($enrollment->id, []);
            $input = [];
            foreach ($own as $subjectId => $byExam) {
                foreach ($byExam as $examId => $mark) {
                    $input[$subjectId][$examId] = ['mark' => $mark->mark === null ? null : (float) $mark->mark, 'absent' => $mark->is_absent];
                }
            }
            $totals = $attendance->get($enrollment->id);

            return [
                'enrollment' => $enrollment,
                'marks' => $own,
                'result' => $calculator->result($subjectIds, $examInput, $input, $totals->absent ?? 0),
                'attendance' => $totals,
                'rank' => null,
            ];
        })->keyBy(fn ($row) => $row['enrollment']->id);

        $ranks = ResultCalculator::ranks($rows->map(fn ($row) => ['result' => $row['result']['result'], 'average' => $row['result']['average']])->all());
        $rows = $rows->map(fn ($row, $id) => ['rank' => $ranks[$id] ?? null] + $row);

        return ['exams' => $exams, 'subjects' => $subjects, 'rows' => $rows, 'calculator' => $calculator];
    }

    /**
     * نمره‌های یک امتحان برای یک بخش: مجموع و فیصدی هر دانش‌آموز.
     *
     * @return Collection<int, array{enrollment: Enrollment, marks: array<int, Mark>, total: float, percent: ?float, complete: bool}>
     */
    public function examResults(Section $section, Exam $exam): Collection
    {
        $section->loadMissing('gradeLevel.subjects');
        $subjectIds = $section->gradeLevel->subjects->pluck('id')->all();
        $enrollments = $this->sectionEnrollments($section);
        $marks = $this->marksFor($enrollments->pluck('id')->all(), [$exam->id]);
        $max = (float) $exam->max_mark * count($subjectIds);

        return $enrollments->map(function (Enrollment $enrollment) use ($marks, $exam, $subjectIds, $max) {
            $own = [];
            $total = 0.0;
            $complete = $subjectIds !== [];
            foreach ($subjectIds as $subjectId) {
                $mark = $marks->get($enrollment->id, [])[$subjectId][$exam->id] ?? null;
                $own[$subjectId] = $mark;
                if (! $mark || (! $mark->is_absent && $mark->mark === null)) {
                    $complete = false;
                } elseif (! $mark->is_absent) {
                    $total += (float) $mark->mark;
                }
            }

            return [
                'enrollment' => $enrollment,
                'marks' => $own,
                'total' => round($total, 2),
                'percent' => $max > 0 ? round($total * 100 / $max, 2) : null,
                'complete' => $complete,
            ];
        })->values();
    }

    /** @return array{finalized: int, counts: array<string, int>} */
    public function finalize(Section $section, User $user): array
    {
        $data = $this->sectionResults($section);

        if ($data['exams']->isEmpty()) {
            throw ValidationException::withMessages(['finalize' => __('exams.finalize_no_exams')]);
        }
        if ($data['exams']->contains(fn (Exam $e) => $e->status !== ExamStatus::Locked)) {
            throw ValidationException::withMessages(['finalize' => __('exams.finalize_unlocked')]);
        }
        $incomplete = $data['rows']->filter(fn ($row) => $row['result']['result'] === null)->count();
        if ($incomplete > 0) {
            throw ValidationException::withMessages(['finalize' => __('exams.finalize_incomplete', ['count' => Digits::toPersian((string) $incomplete)])]);
        }
        if ($data['rows']->isEmpty()) {
            throw ValidationException::withMessages(['finalize' => __('academics.no_students_in_section')]);
        }

        return DB::transaction(function () use ($section, $user, $data) {
            $counts = [];
            foreach ($data['rows'] as $row) {
                $result = $row['result'];
                $row['enrollment']->forceFill([
                    'final_total' => $result['total'],
                    'final_average' => $result['average'],
                    'final_result' => $result['result'],
                    'class_rank' => $row['rank'],
                    'finalized_at' => now(),
                    'finalized_by' => $user->id,
                    'final_snapshot' => $this->snapshot($row, $data),
                ])->save();
                $label = $result['result']->label();
                $counts[$label] = ($counts[$label] ?? 0) + 1;
            }

            $section->loadMissing('gradeLevel');
            $this->logger->log('results.finalized', $section, $section->label, new: $counts, user: $user);

            return ['finalized' => $data['rows']->count(), 'counts' => $counts];
        });
    }

    public function unfinalize(Section $section, User $user, string $reason): int
    {
        return DB::transaction(function () use ($section, $user, $reason) {
            $count = Enrollment::where('section_id', $section->id)->whereNotNull('finalized_at')->update([
                'final_total' => null, 'final_average' => null, 'final_result' => null, 'class_rank' => null,
                'finalized_at' => null, 'finalized_by' => null, 'final_snapshot' => null,
            ]);
            $section->loadMissing('gradeLevel');
            $this->logger->log('results.unfinalized', $section, $section->label.' — '.$reason, new: ['count' => $count], user: $user);

            return $count;
        });
    }

    public function isFinalized(Section $section): bool
    {
        return Enrollment::where('section_id', $section->id)->whereNotNull('finalized_at')->exists();
    }

    /**
     * داده‌ی کارنامه‌های یک بخش (یا یک دانش‌آموز). نهایی‌شده ← از نسخه‌ی ثابت؛ در غیر آن نتیجه‌ی زنده با نشان «موقت».
     *
     * @return Collection<int, array> هر کارنامه: enrollment, provisional, settings, exams, subjects, total, average, failed, result, rank, attendance
     */
    public function reportCards(Section $section, ?Enrollment $only = null): Collection
    {
        $live = null;
        $enrollments = $only ? collect([$only->loadMissing('student')]) : $this->sectionEnrollments($section);

        return $enrollments->map(function (Enrollment $enrollment) use ($section, &$live) {
            if ($enrollment->isFinalized() && is_array($enrollment->final_snapshot)) {
                return ['enrollment' => $enrollment, 'provisional' => false] + $enrollment->final_snapshot;
            }
            $live ??= $this->sectionResults($section);
            $row = $live['rows']->get($enrollment->id);

            return $row ? ['enrollment' => $enrollment, 'provisional' => true] + $this->snapshot($row, $live) : null;
        })->filter()->values();
    }

    /** نسخه‌ی ثابت نتیجه‌ی یک دانش‌آموز (برای ذخیره هنگام نهایی‌کردن و نمایش کارنامه) */
    private function snapshot(array $row, array $data): array
    {
        $result = $row['result'];
        $attendance = $row['attendance'];

        return [
            'settings' => $data['calculator']->settings(),
            'exams' => $data['exams']->map(fn (Exam $e) => [
                'id' => $e->id, 'name' => $e->name, 'max_mark' => (float) $e->max_mark, 'weight' => (float) $e->weight,
            ])->values()->all(),
            'subjects' => $data['subjects']->map(fn ($subject) => [
                'id' => $subject->id,
                'name' => $subject->name,
                'marks' => collect($data['exams'])->mapWithKeys(function (Exam $e) use ($row, $subject) {
                    $mark = $row['marks'][$subject->id][$e->id] ?? null;

                    return [$e->id => $mark ? ['mark' => $mark->mark === null ? null : (float) $mark->mark, 'absent' => $mark->is_absent] : null];
                })->all(),
                'final' => $result['subjects'][$subject->id]['final'] ?? null,
                'passed' => $result['subjects'][$subject->id]['passed'] ?? null,
            ])->values()->all(),
            'total' => $result['total'],
            'average' => $result['average'],
            'failed' => $result['failed'],
            'result' => $result['result']?->value,
            'rank' => $row['rank'],
            'attendance' => [
                'present' => $attendance->present ?? 0, 'late' => $attendance->late ?? 0, 'absent' => $attendance->absent ?? 0,
                'excused' => $attendance->excused ?? 0, 'sick' => $attendance->sick ?? 0, 'total' => $attendance->total ?? 0,
            ],
        ];
    }

    /** @return Collection<int, array<int, array<int, Mark>>> enrollment_id => subject_id => exam_id => Mark */
    private function marksFor(array $enrollmentIds, array $examIds): Collection
    {
        $grouped = [];
        Mark::query()
            ->whereIn('enrollment_id', $enrollmentIds ?: [0])
            ->whereIn('exam_id', $examIds ?: [0])
            ->get()
            ->each(function (Mark $mark) use (&$grouped) {
                $grouped[$mark->enrollment_id][$mark->subject_id][$mark->exam_id] = $mark;
            });

        return collect($grouped);
    }

    /** نتیجه‌ی پیشنهادی ارتقا از روی نتیجه‌ی نهایی */
    public static function promotionSuggestion(?FinalResult $result, bool $lastGrade): ?string
    {
        return match ($result) {
            FinalResult::Passed => $lastGrade ? EnrollmentService::GRADUATE : EnrollmentService::PROMOTE,
            FinalResult::Failed, FinalResult::Deprived => EnrollmentService::REPEAT,
            FinalResult::Conditional => EnrollmentService::SKIP,
            null => null,
        };
    }
}
