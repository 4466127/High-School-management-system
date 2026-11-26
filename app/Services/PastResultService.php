<?php

namespace App\Services;

use App\Enums\FinalResult;
use App\Enums\StudentStatus;
use App\Models\GradeLevel;
use App\Models\Student;
use App\Models\StudentPastResult;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

/**
 * نتیجه‌ی سال‌هایی که در سیستم نیست (مرحله‌ی ۱۴): درج دستی برای فارغ‌های سال‌های قبل یا سال‌های مکتب دیگر.
 * مجموع، اوسط، نتیجه و درجه اگر خالی باشد از نمره‌ی مضامین حساب می‌شود (همان قاعده‌های تنظیمات).
 * هیچ سطری حذف نمی‌شود؛ باطل با دلیل. هر تغییر در activity_logs.
 */
class PastResultService
{
    public function __construct(
        private readonly ActivityLogger $logger,
        private readonly StudentService $students,
    ) {}

    /**
     * @param  array{grade_level_id?: ?int, grade_name?: ?string, year_name: string, school_name?: ?string, total?: mixed, average?: mixed,
     *   result?: ?string, subjects?: list<array{name?: ?string, mark?: mixed}>, note?: ?string}  $data
     */
    public function create(Student $student, array $data, User $user): StudentPastResult
    {
        return DB::transaction(function () use ($student, $data, $user) {
            $row = new StudentPastResult($this->normalize($data));
            $row->student_id = $student->id;
            $row->created_by = $user->id;
            $row->save();

            $this->logger->log('student.past_result_added', $student, $student->full_name.' — '.$row->grade_name.' '.$row->year_name,
                new: $this->summary($row), user: $user);

            return $row;
        });
    }

    public function update(StudentPastResult $row, array $data, User $user): StudentPastResult
    {
        $this->ensureActive($row);

        return DB::transaction(function () use ($row, $data, $user) {
            $old = $this->summary($row);
            $row->fill($this->normalize($data))->save();
            $this->logger->log('student.past_result_updated', $row->student, $row->student->full_name.' — '.$row->grade_name.' '.$row->year_name,
                $old, $this->summary($row), $user);

            return $row;
        });
    }

    public function cancel(StudentPastResult $row, string $reason, User $user): void
    {
        $this->ensureActive($row);
        DB::transaction(function () use ($row, $reason, $user) {
            $row->forceFill(['cancelled_at' => now(), 'cancelled_by' => $user->id, 'cancel_reason' => $reason])->save();
            $this->logger->log('student.past_result_cancelled', $row->student, $row->student->full_name.' — '.$row->grade_name.' '.$row->year_name.' — '.$reason,
                $this->summary($row), user: $user);
        });
    }

    /**
     * ثبت دانش‌آموز سابق (فارغ سال‌های قبل که در سیستم نبود) با نتیجه‌ی سال‌هایش، یک‌جا.
     *
     * @param  array<string, mixed>  $student  فیلدهای Student (تاریخ‌ها میلادی)
     * @param  array{status: string, left_on?: ?string, reason?: ?string, certificate_no?: ?string, certificate_date?: ?string, certificate_note?: ?string}  $leaving
     * @param  list<array>  $results  نتیجه‌ی هر سال (مثل create)؛ سطر بی‌سال نادیده گرفته می‌شود
     */
    public function createFormerStudent(array $student, array $leaving, array $results, User $user): Student
    {
        $status = StudentStatus::from($leaving['status']);

        return DB::transaction(function () use ($student, $leaving, $results, $user, $status) {
            $model = $this->students->create($student);
            $model->forceFill([
                'status' => $status,
                'status_changed_on' => $leaving['left_on'] ?? null,
                'status_reason' => ($leaving['reason'] ?? '') ?: __('past_results.former_reason'),
                'certificate_no' => $status === StudentStatus::Graduated ? (($leaving['certificate_no'] ?? '') ?: null) : null,
                'certificate_date' => $status === StudentStatus::Graduated ? ($leaving['certificate_date'] ?? null) : null,
                'certificate_note' => $status === StudentStatus::Graduated ? (($leaving['certificate_note'] ?? '') ?: null) : null,
            ])->save();

            $this->logger->log('student.former_added', $model, $model->full_name.' — '.$status->label(),
                new: ['status' => $status->value, 'certificate_no' => (string) $model->certificate_no], user: $user);

            foreach ($results as $result) {
                if (trim((string) ($result['year_name'] ?? '')) === '') {
                    continue;
                }
                $this->create($model, $result, $user);
            }

            return $model;
        });
    }

    /** خانه‌های فرم ← ستون‌ها؛ حساب خودکار خانه‌های خالی */
    public function normalize(array $data): array
    {
        $calculator = ResultCalculator::fromSettings();
        $level = ! empty($data['grade_level_id']) ? GradeLevel::find($data['grade_level_id']) : null;

        $subjects = collect($data['subjects'] ?? [])
            ->filter(fn ($s) => is_array($s) && trim((string) ($s['name'] ?? '')) !== '' && is_numeric($s['mark'] ?? null))
            ->map(fn ($s) => [
                'name' => mb_substr(trim((string) $s['name']), 0, 100),
                'mark' => round((float) $s['mark'], 2),
                'passed' => (float) $s['mark'] >= $calculator->passPercent,
            ])->values()->all();

        $total = is_numeric($data['total'] ?? null) ? round((float) $data['total'], 2)
            : ($subjects ? round(array_sum(array_column($subjects, 'mark')), 2) : null);
        $average = is_numeric($data['average'] ?? null) ? round((float) $data['average'], 2)
            : ($subjects ? round(array_sum(array_column($subjects, 'mark')) / count($subjects), 2) : null);

        $result = FinalResult::tryFrom((string) ($data['result'] ?? ''));
        if (! $result && $subjects) {
            $failed = count(array_filter($subjects, fn ($s) => ! $s['passed']));
            $result = match (true) {
                $failed === 0 => FinalResult::Passed,
                $calculator->conditionalMaxFailed > 0 && $failed <= $calculator->conditionalMaxFailed => FinalResult::Conditional,
                default => FinalResult::Failed,
            };
        } elseif (! $result && $average !== null) {
            $result = $average >= $calculator->passPercent ? FinalResult::Passed : FinalResult::Failed;
        }

        $gradeName = $level?->name ?? trim((string) ($data['grade_name'] ?? ''));
        if ($gradeName === '') {
            throw ValidationException::withMessages(['grade_level_id' => __('past_results.grade_required')]);
        }

        return [
            'grade_level_id' => $level?->id,
            'grade_name' => mb_substr($gradeName, 0, 100),
            'year_name' => mb_substr(trim((string) $data['year_name']), 0, 20),
            'school_name' => ($s = trim((string) ($data['school_name'] ?? ''))) !== '' ? mb_substr($s, 0, 200) : null,
            'total' => $total,
            'average' => $average,
            'result' => $result?->value,
            'grade_letter' => $calculator->grade($result, $average),
            'subjects' => $subjects ?: null,
            'note' => ($n = trim((string) ($data['note'] ?? ''))) !== '' ? mb_substr($n, 0, 500) : null,
        ];
    }

    private function ensureActive(StudentPastResult $row): void
    {
        if ($row->isCancelled()) {
            throw ValidationException::withMessages(['reason' => __('past_results.already_cancelled')]);
        }
    }

    private function summary(StudentPastResult $row): array
    {
        return [
            'grade' => $row->grade_name,
            'year' => $row->year_name,
            'school' => (string) $row->school_name,
            'average' => (string) $row->average,
            'result' => (string) $row->result?->value,
            'subjects' => count($row->subjects ?? []),
        ];
    }
}
