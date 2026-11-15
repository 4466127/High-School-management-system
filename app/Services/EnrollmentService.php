<?php

namespace App\Services;

use App\Enums\EnrollmentStatus;
use App\Enums\StudentStatus;
use App\Models\AcademicYear;
use App\Models\Enrollment;
use App\Models\Section;
use App\Models\Student;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

/**
 * شمولیت سالانه، تغییر بخش، انتقال، ترک تحصیل، ارتقای گروهی.
 * قاعده‌ی اصلی: سطر سال‌های قبل هرگز ویرایش نمی‌شود؛ ارتقا فقط status آن را می‌بندد و برای سال تازه سطر جدید می‌سازد.
 */
class EnrollmentService
{
    public const PROMOTE = 'promote';

    public const REPEAT = 'repeat';

    public const GRADUATE = 'graduate';

    public const SKIP = 'skip';

    public function __construct(private readonly ActivityLogger $logger) {}

    /** شامل کردن دانش‌آموز در یک بخش؛ اگر همین سال رفته بود (منتقل/ترک)، همان سطر دوباره فعال می‌شود. */
    public function enroll(Student $student, Section $section, ?string $date = null): Enrollment
    {
        if (in_array($student->status, [StudentStatus::Inactive, StudentStatus::Graduated], true) || $student->trashed()) {
            throw ValidationException::withMessages(['section_id' => __('academics.cannot_enroll_status', ['status' => $student->status->label()])]);
        }

        return DB::transaction(function () use ($student, $section, $date) {
            $existing = Enrollment::where('student_id', $student->id)
                ->where('academic_year_id', $section->academic_year_id)
                ->lockForUpdate()
                ->first();

            if ($existing && $existing->isActive()) {
                throw ValidationException::withMessages(['section_id' => __('academics.already_enrolled', ['section' => $existing->section->label])]);
            }
            if ($existing && ! in_array($existing->status, [EnrollmentStatus::Transferred, EnrollmentStatus::Dropped], true)) {
                throw ValidationException::withMessages(['section_id' => __('academics.year_closed_for_student')]);
            }

            $attributes = [
                'section_id' => $section->id,
                'roll_no' => $this->nextRollNo($section),
                'enrolled_on' => $date ?? now()->toDateString(),
                'status' => EnrollmentStatus::Active,
                'left_on' => null,
                'leave_reason' => null,
            ];

            $enrollment = $existing
                ? tap($existing)->update($attributes)
                : Enrollment::create($attributes + ['student_id' => $student->id, 'academic_year_id' => $section->academic_year_id]);

            if ($student->status !== StudentStatus::Active) {
                $student->forceFill(['status' => StudentStatus::Active, 'status_reason' => null, 'status_changed_on' => now()->toDateString()])->save();
            }

            $this->logger->log($existing ? 'student.re_enrolled' : 'student.enrolled', $student,
                $student->full_name.' — '.$section->label, new: ['section' => $section->label, 'roll_no' => $enrollment->roll_no]);

            return $enrollment;
        });
    }

    public function changeSection(Enrollment $enrollment, Section $target, ?string $reason = null): void
    {
        if (! $enrollment->isActive() || $target->academic_year_id !== $enrollment->academic_year_id) {
            throw ValidationException::withMessages(['section_id' => __('academics.invalid_target_section')]);
        }
        if ($target->id === $enrollment->section_id) {
            return;
        }

        DB::transaction(function () use ($enrollment, $target, $reason) {
            $old = $enrollment->section->label;
            $enrollment->update(['section_id' => $target->id, 'roll_no' => $this->nextRollNo($target)]);

            $this->logger->log('student.section_changed', $enrollment->student,
                $enrollment->student->full_name.($reason ? ' — '.$reason : ''), ['section' => $old], ['section' => $target->label]);
        });
    }

    /** انتقال به مکتب دیگر یا ترک تحصیل در جریان سال */
    public function leave(Enrollment $enrollment, EnrollmentStatus $status, ?string $date, string $reason): void
    {
        if (! in_array($status, [EnrollmentStatus::Transferred, EnrollmentStatus::Dropped], true) || ! $enrollment->isActive()) {
            throw ValidationException::withMessages(['status' => __('academics.invalid_leave')]);
        }

        DB::transaction(function () use ($enrollment, $status, $date, $reason) {
            $date ??= now()->toDateString();
            $enrollment->update(['status' => $status, 'left_on' => $date, 'leave_reason' => $reason]);

            $student = $enrollment->student;
            $student->forceFill([
                'status' => $status === EnrollmentStatus::Transferred ? StudentStatus::Transferred : StudentStatus::Dropped,
                'status_reason' => $reason,
                'status_changed_on' => $date,
            ])->save();

            $this->logger->log($status === EnrollmentStatus::Transferred ? 'student.transferred' : 'student.dropped', $student,
                $student->full_name.' — '.$reason, ['section' => $enrollment->section->label], ['status' => $status->value]);
        });
    }

    /**
     * ارتقای گروهی یک بخش به سال تازه.
     *
     * @param  array<int, string>  $decisions  enrollment_id => promote|repeat|graduate|skip
     * @return array{promoted:int, repeated:int, graduated:int, skipped:int}
     */
    public function promote(Section $source, AcademicYear $targetYear, array $decisions, ?Section $promoteTo, ?Section $repeatIn, ?string $date = null): array
    {
        $source->loadMissing('gradeLevel', 'academicYear');
        $nextGrade = $source->gradeLevel->next();

        $errors = [];
        if ($targetYear->id === $source->academic_year_id) {
            $errors['target_year_id'] = __('academics.promotion_same_year');
        }
        $wants = array_count_values($decisions);
        if (($wants[self::PROMOTE] ?? 0) > 0) {
            if (! $nextGrade) {
                $errors['promote_section_id'] = __('academics.promotion_no_next_grade');
            } elseif (! $promoteTo || $promoteTo->academic_year_id !== $targetYear->id || $promoteTo->grade_level_id !== $nextGrade->id) {
                $errors['promote_section_id'] = __('academics.promotion_bad_promote_section', ['grade' => $nextGrade->name]);
            }
        }
        if (($wants[self::REPEAT] ?? 0) > 0
            && (! $repeatIn || $repeatIn->academic_year_id !== $targetYear->id || $repeatIn->grade_level_id !== $source->grade_level_id)) {
            $errors['repeat_section_id'] = __('academics.promotion_bad_repeat_section', ['grade' => $source->gradeLevel->name]);
        }
        if (($wants[self::GRADUATE] ?? 0) > 0 && $nextGrade) {
            $errors['decisions'] = __('academics.promotion_graduate_not_last');
        }
        if ($errors) {
            throw ValidationException::withMessages($errors);
        }

        return DB::transaction(function () use ($source, $targetYear, $decisions, $promoteTo, $repeatIn, $date) {
            $date ??= now()->toDateString();
            $summary = ['promoted' => 0, 'repeated' => 0, 'graduated' => 0, 'skipped' => 0];

            $enrollments = $source->activeEnrollments()->with('student')->lockForUpdate()->get();

            foreach ($enrollments as $enrollment) {
                $decision = $decisions[$enrollment->id] ?? self::SKIP;
                $student = $enrollment->student;

                $alreadyInTarget = Enrollment::where('student_id', $student->id)->where('academic_year_id', $targetYear->id)->exists();
                if ($decision === self::SKIP || ($alreadyInTarget && $decision !== self::GRADUATE)) {
                    $summary['skipped']++;

                    continue;
                }

                if ($decision === self::GRADUATE) {
                    $enrollment->update(['status' => EnrollmentStatus::Graduated]);
                    $student->forceFill(['status' => StudentStatus::Graduated, 'status_changed_on' => $date, 'status_reason' => null])->save();
                    $this->logger->log('student.graduated', $student, $student->full_name.' — '.$source->label);
                    $summary['graduated']++;

                    continue;
                }

                $target = $decision === self::PROMOTE ? $promoteTo : $repeatIn;
                $enrollment->update(['status' => $decision === self::PROMOTE ? EnrollmentStatus::Promoted : EnrollmentStatus::Repeated]);

                Enrollment::create([
                    'student_id' => $student->id,
                    'academic_year_id' => $targetYear->id,
                    'section_id' => $target->id,
                    'roll_no' => $this->nextRollNo($target),
                    'enrolled_on' => $date,
                    'status' => EnrollmentStatus::Active,
                ]);

                $this->logger->log($decision === self::PROMOTE ? 'student.promoted' : 'student.repeated', $student, $student->full_name,
                    ['section' => $source->label, 'year' => $source->academicYear->name],
                    ['section' => $target->label, 'year' => $targetYear->name]);
                $summary[$decision === self::PROMOTE ? 'promoted' : 'repeated']++;
            }

            $this->logger->log('section.promoted', $source, $source->label.' → '.$targetYear->name, new: $summary);

            return $summary;
        });
    }

    /** نمبر حاضری از نو، به ترتیب نام (برای دانش‌آموزان فعال یک بخش) */
    public function renumber(Section $section): void
    {
        DB::transaction(function () use ($section) {
            $enrollments = $section->activeEnrollments()
                ->join('students', 'students.id', '=', 'enrollments.student_id')
                ->orderBy('students.first_name')->orderBy('students.last_name')->orderBy('students.father_name')
                ->select('enrollments.*')
                ->get();

            foreach ($enrollments->values() as $i => $enrollment) {
                $enrollment->update(['roll_no' => $i + 1]);
            }

            $this->logger->log('section.renumbered', $section, $section->label, new: ['count' => $enrollments->count()]);
        });
    }

    private function nextRollNo(Section $section): int
    {
        return ((int) Enrollment::where('section_id', $section->id)->where('status', EnrollmentStatus::Active)->max('roll_no')) + 1;
    }
}
