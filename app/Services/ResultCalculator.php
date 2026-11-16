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
 * درجه: رتبه‌ی دانش‌آموزان کامیاب بخش به ترتیب اوسط (برابرها یک درجه: ۱، ۱، ۳).
 */
final class ResultCalculator
{
    public function __construct(
        public readonly float $passPercent = 40,
        public readonly int $conditionalMaxFailed = 2,
        public readonly int $deprivationDays = 0,
    ) {}

    public static function fromSettings(): self
    {
        return new self(
            (float) Setting::get('pass_percent', 40),
            (int) Setting::get('conditional_max_failed_subjects', 2),
            (int) Setting::get('deprivation_absence_days', 0),
        );
    }

    /** @return array{pass_percent: float, conditional_max_failed_subjects: int, deprivation_absence_days: int} */
    public function settings(): array
    {
        return [
            'pass_percent' => $this->passPercent,
            'conditional_max_failed_subjects' => $this->conditionalMaxFailed,
            'deprivation_absence_days' => $this->deprivationDays,
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
     * @return array{subjects: array<int, array{final: ?float, passed: ?bool}>, total: float, average: ?float, failed: int, incomplete: bool, result: ?FinalResult, absent_days: int}
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

        return [
            'subjects' => $subjects,
            'total' => round($total, 2),
            'average' => $incomplete ? null : round($total / count($subjectIds), 2),
            'failed' => $failed,
            'incomplete' => $incomplete,
            'result' => $result,
            'absent_days' => $absentDays,
        ];
    }

    /**
     * درجه (رتبه) دانش‌آموزان کامیاب به ترتیب اوسط.
     *
     * @param  array<int, array{result: ?FinalResult, average: ?float}>  $results  enrollment_id => نتیجه
     * @return array<int, int> enrollment_id => درجه
     */
    public static function ranks(array $results): array
    {
        $passed = array_filter($results, fn ($r) => $r['result'] === FinalResult::Passed && $r['average'] !== null);
        uasort($passed, fn ($a, $b) => $b['average'] <=> $a['average']);

        $ranks = [];
        $position = 0;
        $previous = null;
        $rank = 0;
        foreach ($passed as $id => $r) {
            $position++;
            if ($previous === null || $r['average'] < $previous) {
                $rank = $position;
            }
            $ranks[$id] = $rank;
            $previous = $r['average'];
        }

        return $ranks;
    }
}
