<?php

namespace Tests\Unit;

use App\Enums\FinalResult;
use App\Services\ResultCalculator;
use PHPUnit\Framework\TestCase;

/** فورمول نتیجه با چند روش نمره‌دهی (پذیرش مرحله‌ی ۶). */
class ResultCalculatorTest extends TestCase
{
    private const MID = ['id' => 1, 'max_mark' => 40.0, 'weight' => 40.0];

    private const FINAL = ['id' => 2, 'max_mark' => 60.0, 'weight' => 60.0];

    private function m(?float $mark, bool $absent = false): array
    {
        return ['mark' => $mark, 'absent' => $absent];
    }

    public function test_common_method_40_plus_60_is_a_simple_sum(): void
    {
        $calc = new ResultCalculator;
        $this->assertSame(75.0, $calc->subjectFinal([self::MID, self::FINAL], [1 => $this->m(30), 2 => $this->m(45)]));
        $this->assertSame(100.0, $calc->subjectFinal([self::MID, self::FINAL], [1 => $this->m(40), 2 => $this->m(60)]));
        $this->assertSame(28.75, $calc->subjectFinal([self::MID, self::FINAL], [1 => $this->m(10.5), 2 => $this->m(18.25)]));
    }

    public function test_exams_out_of_100_with_weights(): void
    {
        $calc = new ResultCalculator;
        $exams = [['id' => 1, 'max_mark' => 100.0, 'weight' => 40.0], ['id' => 2, 'max_mark' => 100.0, 'weight' => 60.0]];
        $this->assertSame(68.0, $calc->subjectFinal($exams, [1 => $this->m(50), 2 => $this->m(80)]));
    }

    public function test_three_exams_including_a_counted_monthly_test(): void
    {
        $calc = new ResultCalculator;
        $exams = [
            ['id' => 1, 'max_mark' => 20.0, 'weight' => 20.0],   // ماهوار
            ['id' => 2, 'max_mark' => 30.0, 'weight' => 30.0],   // چهارونیم‌ماهه
            ['id' => 3, 'max_mark' => 50.0, 'weight' => 50.0],   // سالانه
        ];
        $this->assertSame(70.0, $calc->subjectFinal($exams, [1 => $this->m(10), 2 => $this->m(24), 3 => $this->m(36)]));
    }

    public function test_absent_counts_as_zero_and_missing_mark_is_incomplete(): void
    {
        $calc = new ResultCalculator;
        $this->assertSame(60.0, $calc->subjectFinal([self::MID, self::FINAL], [1 => $this->m(null, true), 2 => $this->m(60)]));
        $this->assertNull($calc->subjectFinal([self::MID, self::FINAL], [1 => $this->m(30)]));
        $this->assertNull($calc->subjectFinal([self::MID, self::FINAL], [1 => $this->m(30), 2 => $this->m(null)]));
        $this->assertNull($calc->subjectFinal([], []));
    }

    public function test_passed_conditional_failed_by_number_of_failed_subjects(): void
    {
        $calc = new ResultCalculator(passPercent: 40, conditionalMaxFailed: 2);
        $exams = [self::MID, self::FINAL];
        $mark = fn (float $mid, float $final) => [1 => $this->m($mid), 2 => $this->m($final)];

        $passed = $calc->result([10, 20, 30], $exams, [10 => $mark(30, 40), 20 => $mark(20, 20), 30 => $mark(16, 24)]);
        $this->assertSame(FinalResult::Passed, $passed['result']);   // ۴۰ دقیقاً کامیاب است
        $this->assertSame(0, $passed['failed']);
        $this->assertSame(150.0, $passed['total']);   // ۷۰ + ۴۰ + ۴۰
        $this->assertSame(50.0, $passed['average']);
        $this->assertSame(66.67, $calc->result([10, 20, 30], $exams, [10 => $mark(40, 60), 20 => $mark(20, 30), 30 => $mark(20, 30)])['average']);

        $conditional = $calc->result([10, 20, 30], $exams, [10 => $mark(30, 40), 20 => $mark(10, 10), 30 => $mark(10, 19)]);
        $this->assertSame(FinalResult::Conditional, $conditional['result']);
        $this->assertSame(2, $conditional['failed']);
        $this->assertFalse($conditional['subjects'][20]['passed']);

        $failed = $calc->result([10, 20, 30], $exams, [10 => $mark(5, 10), 20 => $mark(10, 10), 30 => $mark(10, 19)]);
        $this->assertSame(FinalResult::Failed, $failed['result']);
    }

    public function test_settings_change_the_outcome(): void
    {
        $marks = [10 => [1 => $this->m(18), 2 => $this->m(30)]];   // ۴۸ از ۱۰۰
        $this->assertSame(FinalResult::Passed, (new ResultCalculator(40, 2))->result([10], [self::MID, self::FINAL], $marks)['result']);
        $this->assertSame(FinalResult::Conditional, (new ResultCalculator(50, 2))->result([10], [self::MID, self::FINAL], $marks)['result']);
        $this->assertSame(FinalResult::Failed, (new ResultCalculator(50, 0))->result([10], [self::MID, self::FINAL], $marks)['result']);
    }

    public function test_deprived_by_absence_overrides_marks(): void
    {
        $calc = new ResultCalculator(40, 2, deprivationDays: 20);
        $marks = [10 => [1 => $this->m(40), 2 => $this->m(60)]];
        $this->assertSame(FinalResult::Passed, $calc->result([10], [self::MID, self::FINAL], $marks, 20)['result']);
        $this->assertSame(FinalResult::Deprived, $calc->result([10], [self::MID, self::FINAL], $marks, 21)['result']);
        // محروم حتی با نمره‌ی ناقص
        $this->assertSame(FinalResult::Deprived, $calc->result([10], [self::MID, self::FINAL], [], 30)['result']);
        // حد صفر = غیرفعال
        $this->assertSame(FinalResult::Passed, (new ResultCalculator(40, 2, 0))->result([10], [self::MID, self::FINAL], $marks, 200)['result']);
    }

    public function test_incomplete_result_has_no_outcome(): void
    {
        $result = (new ResultCalculator)->result([10, 20], [self::MID, self::FINAL], [10 => [1 => $this->m(30), 2 => $this->m(50)]]);
        $this->assertTrue($result['incomplete']);
        $this->assertNull($result['result']);
        $this->assertNull($result['average']);
    }

    public function test_letter_grade_only_for_passed_students(): void
    {
        $calc = new ResultCalculator(40, 2, 0, 90, 75, 60);
        $this->assertSame('a', $calc->grade(FinalResult::Passed, 90.0));
        $this->assertSame('b', $calc->grade(FinalResult::Passed, 89.99));
        $this->assertSame('c', $calc->grade(FinalResult::Passed, 60.0));
        $this->assertSame('d', $calc->grade(FinalResult::Passed, 45.0));
        $this->assertNull($calc->grade(FinalResult::Conditional, 70.0));
        $this->assertNull($calc->grade(FinalResult::Failed, 95.0));
        $this->assertNull($calc->grade(null, null));

        $result = $calc->result([10], [self::MID, self::FINAL], [10 => [1 => $this->m(38), 2 => $this->m(55)]]);
        $this->assertSame('a', $result['grade']); // ۹۳
    }

    public function test_reexam_replaces_failed_subject_final(): void
    {
        $calc = new ResultCalculator(40, 2, 0, 90, 75, 60);
        $subjects = [
            ['id' => 10, 'final' => 80.0, 'passed' => true],
            ['id' => 20, 'final' => 30.0, 'passed' => false],
            ['id' => 30, 'final' => 25.0, 'passed' => false],
        ];

        $passed = $calc->reexamResult($subjects, [20 => ['mark' => 55.0, 'absent' => false], 30 => ['mark' => 40.0, 'absent' => false]]);
        $this->assertSame(FinalResult::Passed, $passed['result']);
        $this->assertSame(175.0, $passed['total']);
        $this->assertSame(58.33, $passed['average']);
        $this->assertSame('d', $passed['grade']);
        $this->assertSame(55.0, $passed['subjects'][20]['reexam']);
        $this->assertNull($passed['subjects'][10]['reexam']);

        $failed = $calc->reexamResult($subjects, [20 => ['mark' => 70.0, 'absent' => false], 30 => ['mark' => null, 'absent' => true]]);
        $this->assertSame(FinalResult::Failed, $failed['result']);
        $this->assertSame(1, $failed['failed']);
        $this->assertNull($failed['grade']);

        $incomplete = $calc->reexamResult($subjects, [20 => ['mark' => 70.0, 'absent' => false]]);
        $this->assertTrue($incomplete['incomplete']);
        $this->assertNull($incomplete['result']);
    }
}
