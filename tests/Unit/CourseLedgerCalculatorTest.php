<?php

namespace Tests\Unit;

use App\Enums\CourseChannel;
use App\Enums\CourseEntryType;
use App\Services\CourseLedgerCalculator;
use PHPUnit\Framework\TestCase;

class CourseLedgerCalculatorTest extends TestCase
{
    private function e(CourseEntryType $type, int $cents, CourseChannel $channel = CourseChannel::Cashbox, ?int $staff = null): array
    {
        return ['type' => $type, 'channel' => $channel, 'staff_id' => $staff, 'cents' => $cents];
    }

    public function test_direct_donations_do_not_change_cashbox_balance(): void
    {
        $s = CourseLedgerCalculator::summarize([
            $this->e(CourseEntryType::Donation, 1_000_000),
            $this->e(CourseEntryType::Donation, 300_000, CourseChannel::DirectToTeacher, 7),
            $this->e(CourseEntryType::TeacherSalary, 400_000, staff: 7),
            $this->e(CourseEntryType::TeacherSalary, 100_000, staff: 9),
            $this->e(CourseEntryType::Expense, 150_050),
        ]);

        $this->assertSame(1_000_000, $s['donations_cashbox']);
        $this->assertSame(300_000, $s['donations_direct']);
        $this->assertSame(1_300_000, $s['donations_total']);
        $this->assertSame(500_000, $s['salaries']);
        $this->assertSame(150_050, $s['expenses']);
        $this->assertSame(349_950, $s['balance']);
        $this->assertSame(['salary' => 400_000, 'direct' => 300_000, 'total' => 700_000], $s['teachers'][7]);
        $this->assertSame(['salary' => 100_000, 'direct' => 0, 'total' => 100_000], $s['teachers'][9]);
    }

    /** 2.1.0: فیس اشتراک شاگرد بیرونی عاید صندوق است */
    public function test_participant_fees_add_to_cashbox(): void
    {
        $s = CourseLedgerCalculator::summarize([
            $this->e(CourseEntryType::Donation, 100_000),
            $this->e(CourseEntryType::Fee, 50_000),
            $this->e(CourseEntryType::Expense, 30_000),
        ]);
        $this->assertSame(50_000, $s['fees']);
        $this->assertSame(100_000, $s['donations_total']);
        $this->assertSame(120_000, $s['balance']);
        $this->assertSame(500, CourseLedgerCalculator::cashEffect(CourseEntryType::Fee, CourseChannel::Cashbox, 500));
    }

    public function test_empty_ledger_and_cash_effect(): void
    {
        $s = CourseLedgerCalculator::summarize([]);
        $this->assertSame(0, $s['balance']);
        $this->assertSame([], $s['teachers']);

        $this->assertSame(500, CourseLedgerCalculator::cashEffect(CourseEntryType::Donation, CourseChannel::Cashbox, 500));
        $this->assertSame(0, CourseLedgerCalculator::cashEffect(CourseEntryType::Donation, CourseChannel::DirectToTeacher, 500));
        $this->assertSame(-500, CourseLedgerCalculator::cashEffect(CourseEntryType::Expense, CourseChannel::Cashbox, 500));
        $this->assertSame(-500, CourseLedgerCalculator::cashEffect(CourseEntryType::TeacherSalary, CourseChannel::Cashbox, 500));
    }
}
