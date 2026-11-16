<?php

namespace Tests\Unit;

use App\Enums\AttendanceStatus;
use App\Services\AttendanceService;
use App\Support\JalaliDate;
use Tests\TestCase;

class AttendanceHelpersTest extends TestCase
{
    public function test_month_range_of_jalali_months(): void
    {
        [$from, $to] = JalaliDate::monthRange(1405, 8);   // عقرب: ۳۰ روز
        $this->assertSame('2026-10-23', $from->format('Y-m-d'));
        $this->assertSame('2026-11-21', $to->format('Y-m-d'));

        [$from, $to] = JalaliDate::monthRange(1405, 1);   // حمل: ۳۱ روز
        $this->assertSame('2026-03-21', $from->format('Y-m-d'));
        $this->assertSame('2026-04-20', $to->format('Y-m-d'));
    }

    public function test_months_between_crosses_the_year(): void
    {
        $months = JalaliDate::monthsBetween('2026-11-16', '2027-04-01');   // عقرب ۱۴۰۵ تا حمل ۱۴۰۶
        $this->assertSame([[1405, 8], [1405, 9], [1405, 10], [1405, 11], [1405, 12], [1406, 1]], $months);
    }

    public function test_attendance_percent_counts_late_as_present(): void
    {
        $this->assertSame(75.0, AttendanceService::percent(['present' => 2, 'late' => 1, 'absent' => 1, 'excused' => 0, 'sick' => 0]));
        $this->assertNull(AttendanceService::percent(['present' => 0, 'absent' => 0]));
    }

    public function test_status_codes_and_labels(): void
    {
        $this->assertSame('غ', AttendanceStatus::Absent->code());
        $this->assertSame('مریض', AttendanceStatus::Sick->label());
        $this->assertTrue(AttendanceStatus::Late->isPresent());
        $this->assertFalse(AttendanceStatus::Excused->isPresent());
    }
}
