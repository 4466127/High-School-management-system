<?php

namespace App\Services;

use App\Enums\EnrollmentStatus;
use App\Models\Enrollment;
use App\Models\Exam;
use App\Models\Mark;
use App\Models\Section;
use App\Models\Subject;
use App\Models\User;
use App\Support\Digits;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;

/**
 * ثبت و اصلاح نمرات یک مضمون در یک بخش برای یک امتحان.
 * نمره حذف نمی‌شود؛ خالی کردن نمره‌ی ثبت‌شده هم یک «اصلاح» است. هر اصلاح با مقدار قبلی در activity_logs.
 */
class MarkService
{
    public function __construct(private readonly ActivityLogger $logger) {}

    /** دانش‌آموزان ورق نمره: شاملان فعلی بخش و هر کسی از این بخش که در همین امتحان و مضمون نمره دارد */
    public function sheetEnrollments(Exam $exam, Section $section, Subject $subject): Collection
    {
        $withMarks = Mark::query()
            ->where('exam_id', $exam->id)
            ->where('subject_id', $subject->id)
            ->whereHas('enrollment', fn ($q) => $q->where('section_id', $section->id))
            ->pluck('enrollment_id')
            ->all();

        return Enrollment::query()
            ->with('student')
            ->where('section_id', $section->id)
            ->where(fn (Builder $q) => $q
                ->whereIn('status', [EnrollmentStatus::Active, EnrollmentStatus::Promoted, EnrollmentStatus::Repeated, EnrollmentStatus::Graduated])
                ->orWhereIn('id', $withMarks ?: [0]))
            ->orderByRaw('roll_no IS NULL, roll_no')
            ->orderBy('id')
            ->get()
            ->keyBy('id');
    }

    /** @return Collection<int, Mark> با کلید enrollment_id */
    public function sheetMarks(Exam $exam, Subject $subject, Collection $enrollments): Collection
    {
        return Mark::query()
            ->where('exam_id', $exam->id)
            ->where('subject_id', $subject->id)
            ->whereIn('enrollment_id', $enrollments->keys()->all() ?: [0])
            ->get()
            ->keyBy('enrollment_id');
    }

    /**
     * @param  array<int|string, ?string>  $marks  enrollment_id => نمره (خالی = بدون نمره)
     * @param  array<int|string, mixed>  $absent  enrollment_id => 1 (غایب در امتحان)
     * @param  array<int|string, ?string>  $notes
     * @return array{entered: int, changed: int}
     */
    public function save(Exam $exam, Section $section, Subject $subject, array $marks, array $absent, array $notes, User $user): array
    {
        return DB::transaction(function () use ($exam, $section, $subject, $marks, $absent, $notes, $user) {
            $enrollments = $this->sheetEnrollments($exam, $section, $subject);
            $existing = Mark::query()
                ->where('exam_id', $exam->id)
                ->where('subject_id', $subject->id)
                ->whereIn('enrollment_id', $enrollments->keys()->all() ?: [0])
                ->lockForUpdate()
                ->get()
                ->keyBy('enrollment_id');

            $entered = 0;
            $old = [];
            $new = [];

            foreach ($enrollments as $id => $enrollment) {
                $isAbsent = ! empty($absent[$id]);
                $raw = trim(Digits::toLatin((string) ($marks[$id] ?? '')));
                $value = $isAbsent || $raw === '' ? null : round((float) $raw, 2);
                $note = trim((string) ($notes[$id] ?? '')) ?: null;
                $mark = $existing->get($id);
                $who = Digits::toPersian((string) $enrollment->roll_no).' — '.$enrollment->student->full_name;

                if (! $mark) {
                    if ($value === null && ! $isAbsent && $note === null) {
                        continue;
                    }
                    Mark::create([
                        'exam_id' => $exam->id, 'enrollment_id' => $id, 'subject_id' => $subject->id,
                        'mark' => $value, 'is_absent' => $isAbsent, 'note' => $note,
                        'entered_by' => $user->id, 'updated_by' => $user->id,
                    ]);
                    $entered++;

                    continue;
                }

                $before = $mark->mark === null ? null : (float) $mark->mark;
                if ($before !== $value || $mark->is_absent !== $isAbsent || $mark->note !== $note) {
                    $old[$who] = $this->describe($before, $mark->is_absent, $mark->note);
                    $new[$who] = $this->describe($value, $isAbsent, $note);
                    $mark->update(['mark' => $value, 'is_absent' => $isAbsent, 'note' => $note, 'updated_by' => $user->id]);
                }
            }

            $section->loadMissing('gradeLevel');
            $description = $exam->name.' — '.$section->shortLabel.' — '.$subject->name;
            if ($entered > 0) {
                $this->logger->log('marks.entered', $exam, $description, new: ['count' => $entered], user: $user);
            }
            if ($new !== []) {
                $this->logger->log('marks.updated', $exam, $description, $old, $new, $user);
            }

            return ['entered' => $entered, 'changed' => count($new)];
        });
    }

    private function describe(?float $mark, bool $absent, ?string $note): string
    {
        $text = $absent ? __('exams.absent') : ($mark === null ? '—' : Digits::toPersian(self::format($mark)));

        return $text.($note ? ' ('.$note.')' : '');
    }

    /** ۲۸.۵۰ ← ۲۸.۵ ؛ ۳۰.۰۰ ← ۳۰ */
    public static function format(float|string|null $mark): string
    {
        if ($mark === null || $mark === '') {
            return '';
        }

        return rtrim(rtrim(number_format((float) $mark, 2, '.', ''), '0'), '.');
    }
}
