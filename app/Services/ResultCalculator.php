<?php

namespace App\Services;

use App\Enums\FinalResult;
use App\Models\Setting;

/**
 * محاسبه‌ی نتیجه (بدون پایگاه داده؛ تست Unit دارد). هیچ عددی ثابت نیست، همه از تنظیمات و امتحان‌ها.
 *
 * نمره‌ی نهایی مضمون (از ۱۰۰) = Σ (نمره ÷ نمره‌ی کامل × وزن) ÷ Σ وزن × ۱۰۰   — فقط امتحان‌های «در نتیجه حساب شود»
 * غایب در امتحان = صفر. اگر نمره‌ی یک امتحان حساب‌شونده ثبت نشده باشد، مضمون «ناقص» است (null).
 * کامیابی مضمون: نمره‌ی نهایی ≥ pass_percent.
 * نتیجه: محروم (غیرحاضر بیشتر از حد) ← ناقص (null) ← کامیاب (هیچ ناکام) ← مشروط (ناکام‌ها ≤ حد) ← ناکام.
 * درجه‌ی حرفی (فقط کامیاب‌ها) از اوسط: الف ≥ grade_a_min، ب ≥ grade_b_min، ج ≥ grade_c_min، د = بقیه‌ی کامیاب‌ها.
 * مشروطی: نمره‌ی مشروطی (از ۱۰۰) جای نمره‌ی نهایی مضمون ناکام را می‌گیرد؛ همه‌ی مضامین ناکام کامیاب ← کامیاب، در غیر آن ← ناکام.
 */
final class ResultCalculator
{
    public const GRADES = ['a', 'b', 'c', 'd'];

    public function __construct(
        public readonly float $passPercent = 40,
        public readonly int $conditionalMaxFailed = 2,
        public readonly int $deprivationDays = 0,
        public readonly float $gradeAMin = 90,
        public readonly float $gradeBMin = 75,
        public readonly float $gradeCMin = 60,
    ) {}

    public static function fromSettings(): self
    {
        return new self(
            (float) Setting::get('pass_percent', 40),
            (int) Setting::get('conditional_max_failed_subjects', 2),
            (int) Setting::get('deprivation_absence_days', 0),
            (float) Setting::get('grade_a_min', 90),
            (float) Setting::get('grade_b_min', 75),
            (float) Setting::get('grade_c_min', 60),
        );
    }

    /** همان قاعده‌هایی که در نسخه‌ی ثابت ذخیره شده بود (کلیدهای نبوده ← تنظیمات فعلی) */
    public static function fromSnapshot(array $settings): self
    {
        $current = self::fromSettings();

        return new self(
            (float) ($settings['pass_percent'] ?? $current->passPercent),
            (int) ($settings['conditional_max_failed_subjects'] ?? $current->conditionalMaxFailed),
            (int) ($settings['deprivation_absence_days'] ?? $current->deprivationDays),
            (float) ($settings['grade_a_min'] ?? $current->gradeAMin),
            (float) ($settings['grade_b_min'] ?? $current->gradeBMin),
            (float) ($settings['grade_c_min'] ?? $current->gradeCMin),
        );
    }

    /** @return array{pass_percent: float, conditional_max_failed_subjects: int, deprivation_absence_days: int, grade_a_min: float, grade_b_min: float, grade_c_min: float} */
    public function settings(): array
    {
        return [
            'pass_percent' => $this->passPercent,
            'conditional_max_failed_subjects' => $this->conditionalMaxFailed,
            'deprivation_absence_days' => $this->deprivationDays,
            'grade_a_min' => $this->gradeAMin,
            'grade_b_min' => $this->gradeBMin,
            'grade_c_min' => $this->gradeCMin,
        ];
    }

    /**
     * @param  list<array{id: int, max_mark: float, weight: float}>  $exams  امتحان‌های حساب‌شونده
     * @param  array<int, array{mark: ?float, absent: bool}>  $marks  exam_id => نمره
     */
    public function subjectFinal(array $exams, array $marks): ?float
    {
        $weights = array_sum(array_column($exams, 'weight'));
        if ($exams === [] || $weights <= 0) {
            return null;
        }

        $sum = 0.0;
        foreach ($exams as $exam) {
            $mark = $marks[$exam['id']] ?? null;
            if ($mark === null || (! $mark['absent'] && $mark['mark'] === null) || $exam['max_mark'] <= 0) {
                return null;
            }
            $value = $mark['absent'] ? 0.0 : (float) $mark['mark'];
            $sum += $value / $exam['max_mark'] * $exam['weight'];
        }

        return round($sum / $weights * 100, 2);
    }

    /**
     * نتیجه‌ی یک دانش‌آموز.
     *
     * @param  list<int>  $subjectIds  مضامین صنف
     * @param  list<array{id: int, max_mark: float, weight: float}>  $exams
     * @param  array<int, array<int, array{mark: ?float, absent: bool}>>  $marks  subject_id => exam_id => نمره
     * @return array{subjects: array<int, array{final: ?float, passed: ?bool}>, total: float, average: ?float, failed: int, incomplete: bool, result: ?FinalResult, grade: ?string, absent_days: int}
     */
    public function result(array $subjectIds, array $exams, array $marks, int $absentDays = 0): array
    {
        $subjects = [];
        $total = 0.0;
        $failed = 0;
        $incomplete = $subjectIds === [];

        foreach ($subjectIds as $subjectId) {
            $final = $this->subjectFinal($exams, $marks[$subjectId] ?? []);
            $passed = $final === null ? null : $final >= $this->passPercent;
            $subjects[$subjectId] = ['final' => $final, 'passed' => $passed];

            if ($final === null) {
                $incomplete = true;
            } else {
                $total += $final;
                $failed += $passed ? 0 : 1;
            }
        }

        $deprived = $this->deprivationDays > 0 && $absentDays > $this->deprivationDays;

        $result = match (true) {
            $deprived => FinalResult::Deprived,
            $incomplete => null,
            $failed === 0 => FinalResult::Passed,
            $this->conditionalMaxFailed > 0 && $failed <= $this->conditionalMaxFailed => FinalResult::Conditional,
            default => FinalResult::Failed,
        };
        $average = $incomplete ? null : round($total / count($subjectIds), 2);

        return [
            'subjects' => $subjects,
            'total' => round($total, 2),
            'average' => $average,
            'failed' => $failed,
            'incomplete' => $incomplete,
            'result' => $result,
            'grade' => $this->grade($result, $average),
            'absent_days' => $absentDays,
        ];
    }

    /** درجه‌ی حرفی (a, b, c, d) فقط برای کامیاب‌ها */
    public function grade(?FinalResult $result, ?float $average): ?string
    {
        if ($result !== FinalResult::Passed || $average === null) {
            return null;
        }

        return match (true) {
            $average >= $this->gradeAMin => 'a',
            $average >= $this->gradeBMin => 'b',
            $average >= $this->gradeCMin => 'c',
            default => 'd',
        };
    }

    /**
     * نتیجه‌ی بعد از امتحان مشروطی.
     *
     * @param  list<array{id: int, final: ?float, passed: ?bool}>  $subjects  مضامین نتیجه‌ی نهایی‌شده
     * @param  array<int, array{mark: ?float, absent: bool}>  $reexam  subject_id => نمره‌ی مشروطی (فقط مضامین ناکام)
     * @return array{subjects: array<int, array{final: ?float, reexam: ?float, passed: ?bool}>, total: float, average: ?float, failed: int, incomplete: bool, result: ?FinalResult, grade: ?string}
     */
    public function reexamResult(array $subjects, array $reexam): array
    {
        $out = [];
        $total = 0.0;
        $failed = 0;
        $incomplete = $subjects === [];

        foreach ($subjects as $subject) {
            $id = $subject['id'];
            $row = ['final' => $subject['final'], 'reexam' => null, 'passed' => $subject['passed']];

            if ($subject['passed'] === false) {
                $mark = $reexam[$id] ?? null;
                if ($mark === null || (! $mark['absent'] && $mark['mark'] === null)) {
                    $incomplete = true;
                    $row['passed'] = null;
                } else {
                    $row['reexam'] = $mark['absent'] ? 0.0 : round((float) $mark['mark'], 2);
                    $row['passed'] = $row['reexam'] >= $this->passPercent;
                }
            }

            $counted = $row['reexam'] ?? $row['final'];
            if ($counted === null) {
                $incomplete = true;
            } else {
                $total += $counted;
            }
            $failed += $row['passed'] === false ? 1 : 0;
            $out[$id] = $row;
        }

        $result = $incomplete ? null : ($failed === 0 ? FinalResult::Passed : FinalResult::Failed);
        $average = $incomplete ? null : round($total / count($subjects), 2);

        return [
            'subjects' => $out,
            'total' => round($total, 2),
            'average' => $average,
            'failed' => $failed,
            'incomplete' => $incomplete,
            'result' => $result,
            'grade' => $this->grade($result, $average),
        ];
    }
}
