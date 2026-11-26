<?php

namespace App\Services;

use App\Enums\EnrollmentStatus;
use App\Enums\FinalResult;
use App\Models\AttendanceRecord;
use App\Models\Enrollment;
use App\Models\Setting;
use App\Models\Student;
use App\Models\User;
use App\Support\Money;
use Illuminate\Validation\ValidationException;
use JsonException;

/**
 * سوابق تحصیلی یک دانش‌آموز برای چاپ (سند انتقال، سوابق)، بایگانی و فایل خروجی برای مکتب مقصد (مرحله‌ی ۱۳).
 * فقط خواندن؛ نتیجه‌ی نهایی‌شده از final_snapshot می‌آید (همان که در کارنامه است).
 */
class StudentRecordService
{
    public const PACKAGE_FORMAT = 'maktab-student-record';

    public const PACKAGE_VERSION = 1;

    public function __construct(
        private readonly ActivityLogger $logger,
        private readonly FinanceService $finance,
    ) {}

    /**
     * @return list<array{enrollment: Enrollment, year: string, grade: string, section: string, status: string, enrolled_on: ?string,
     *   left_on: ?string, finalized: bool, result: ?string, average: ?float, total: ?float, letter: ?string, subjects: list<array>, attendance: array<string,int>}>
     */
    public function history(Student $student): array
    {
        $enrollments = Enrollment::query()
            ->with(['academicYear', 'section.gradeLevel'])
            ->where('student_id', $student->id)
            ->join('academic_years', 'academic_years.id', '=', 'enrollments.academic_year_id')
            ->orderBy('academic_years.start_date')
            ->select('enrollments.*')
            ->get();

        $attendance = AttendanceRecord::query()
            ->whereIn('enrollment_id', $enrollments->pluck('id')->all() ?: [0])
            ->selectRaw('enrollment_id, status, count(*) as n')
            ->groupBy('enrollment_id', 'status')
            ->get()
            ->groupBy('enrollment_id');

        return $enrollments->map(function (Enrollment $e) use ($attendance) {
            $snapshot = $e->finalized_at ? ($e->final_snapshot ?? []) : [];
            $counts = ['present' => 0, 'late' => 0, 'absent' => 0, 'excused' => 0, 'sick' => 0];
            foreach ($attendance->get($e->id, collect()) as $row) {
                $counts[$row->status instanceof \BackedEnum ? $row->status->value : $row->status] = (int) $row->n;
            }

            return [
                'enrollment' => $e,
                'year' => (string) $e->academicYear->name,
                'grade' => (string) $e->section->gradeLevel->name,
                'section' => (string) $e->section->name,
                'status' => $e->status->value,
                'enrolled_on' => $e->enrolled_on?->toDateString(),
                'left_on' => $e->left_on?->toDateString(),
                'finalized' => $e->finalized_at !== null,
                'result' => $e->final_result?->value,
                'average' => $e->final_average !== null ? (float) $e->final_average : null,
                'total' => $e->final_total !== null ? (float) $e->final_total : null,
                'letter' => $e->final_grade,
                'subjects' => collect($snapshot['subjects'] ?? [])->map(fn ($s) => [
                    'name' => $s['name'], 'final' => $s['reexam'] ?? $s['final'], 'passed' => $s['passed'],
                ])->values()->all(),
                'attendance' => $counts,
            ];
        })->all();
    }

    /** مانده‌ی فیس (سنت) — فقط اگر مدیریت فیس روشن است */
    public function outstandingCents(Student $student): int
    {
        if (! Setting::enabled('fees_enabled')) {
            return 0;
        }

        return (int) $this->finance->invoicesFor($student, onlyOpen: true)->sum('remaining_cents');
    }

    /** فایل سوابق برای مکتب مقصد (JSON). معلومات حساس فقط همان که مکتب مقصد برای ثبت لازم دارد. */
    public function package(Student $student, User $user, ?array $transfer = null): array
    {
        $history = collect($this->history($student))->map(fn ($h) => collect($h)->except('enrollment')->all())->all();

        $this->logger->log('student.record_exported', $student, $student->full_name, user: $user);

        return [
            'format' => self::PACKAGE_FORMAT,
            'version' => self::PACKAGE_VERSION,
            'exported_at' => now()->toIso8601String(),
            'app_version' => config('app.version'),
            'school' => [
                'name' => (string) Setting::get('school_name'),
                'code' => (string) Setting::get('school_code'),
                'province' => (string) Setting::get('province'),
                'district' => (string) Setting::get('district'),
            ],
            'student' => [
                'admission_no' => $student->admission_no,
                'first_name' => $student->first_name,
                'last_name' => $student->last_name,
                'father_name' => $student->father_name,
                'grandfather_name' => $student->grandfather_name,
                'gender' => $student->gender?->value,
                'birth_date' => $student->birth_date?->toDateString(),
                'tazkira_no' => $student->tazkira_no,
                'province' => $student->province,
                'district' => $student->district,
                'admission_date' => $student->admission_date?->toDateString(),
            ],
            'history' => $history,
            'transfer' => $transfer,
        ];
    }

    /**
     * بازکردن و بررسی فایل سوابق رسیده از مکتب قبلی.
     *
     * @throws ValidationException
     */
    public function parsePackage(string $contents): array
    {
        $fail = fn () => ValidationException::withMessages(['package' => __('transfers.package_invalid')]);
        try {
            $data = json_decode($contents, true, 12, JSON_THROW_ON_ERROR);
        } catch (JsonException) {
            throw $fail();
        }
        if (! is_array($data) || ($data['format'] ?? null) !== self::PACKAGE_FORMAT || ! is_array($data['student'] ?? null) || ! is_array($data['history'] ?? null)) {
            throw $fail();
        }

        // فقط کلیدهای شناخته‌شده و متن کوتاه نگه داشته می‌شود (داده‌ی بیرونی)
        $text = fn ($v, int $max = 200) => is_scalar($v) ? mb_substr((string) $v, 0, $max) : null;
        $num = fn ($v) => is_numeric($v) ? round((float) $v, 2) : null;

        return [
            'format' => self::PACKAGE_FORMAT,
            'version' => (int) ($data['version'] ?? 1),
            'exported_at' => $text($data['exported_at'] ?? null, 40),
            'school' => array_map(fn ($v) => $text($v), array_intersect_key((array) ($data['school'] ?? []), array_flip(['name', 'code', 'province', 'district']))),
            'student' => array_map(fn ($v) => $text($v), array_intersect_key($data['student'], array_flip(['admission_no', 'first_name', 'last_name', 'father_name', 'grandfather_name', 'gender', 'birth_date', 'tazkira_no']))),
            'history' => collect($data['history'])->take(20)->filter(fn ($v) => is_array($v))->map(fn ($h) => [
                'year' => $text($h['year'] ?? null, 20),
                'grade' => $text($h['grade'] ?? null, 100),
                'section' => $text($h['section'] ?? null, 50),
                'status' => $text($h['status'] ?? null, 20),
                'result' => $text($h['result'] ?? null, 20),
                'average' => $num($h['average'] ?? null),
                'total' => $num($h['total'] ?? null),
                'letter' => $text($h['letter'] ?? null, 5),
                'subjects' => collect(is_array($h['subjects'] ?? null) ? $h['subjects'] : [])->take(40)->filter(fn ($v) => is_array($v))->map(fn ($s) => [
                    'name' => $text($s['name'] ?? null, 100), 'final' => $num($s['final'] ?? null), 'passed' => (bool) ($s['passed'] ?? false),
                ])->values()->all(),
                'attendance' => array_map(fn ($v) => (int) $v, array_intersect_key(is_array($h['attendance'] ?? null) ? $h['attendance'] : [], array_flip(['present', 'late', 'absent', 'excused', 'sick']))),
            ])->values()->all(),
        ];
    }

    public static function resultLabel(?string $result): string
    {
        return $result && FinalResult::tryFrom($result) ? FinalResult::from($result)->label() : '—';
    }

    public static function statusLabel(?string $status): string
    {
        return $status && EnrollmentStatus::tryFrom($status) ? EnrollmentStatus::from($status)->label() : (string) $status;
    }

    public static function money(int $cents): string
    {
        return Money::format(Money::fromCents($cents));
    }
}
