<?php

namespace Tests\Feature\Attendance;

use App\Enums\Role;
use App\Models\Enrollment;
use App\Models\Section;
use App\Models\Setting;
use App\Models\Staff;
use App\Models\Student;
use App\Models\User;
use App\Services\AttendanceService;
use App\Services\EnrollmentService;
use Carbon\Carbon;
use Carbon\CarbonImmutable;
use Tests\TestCase;

/** گزارش ماهوار، خلاصه‌ی صنف‌ها، غیابت زیاد و زبانه‌ی حاضری پرونده. */
class AttendanceReportTest extends TestCase
{
    private User $office;

    private User $teacher;

    private Section $section;

    private Section $otherSection;

    private Enrollment $often;   // غیابت زیاد

    private Enrollment $good;

    protected function setUp(): void
    {
        parent::setUp();
        Carbon::setTestNow('2026-11-16 09:00:00');
        CarbonImmutable::setTestNow('2026-11-16 09:00:00');

        $year = $this->makeYear();
        $staff = Staff::factory()->create();
        $this->teacher = $this->userWithRole(Role::Teacher, ['staff_id' => $staff->id]);
        $this->office = $this->userWithRole(Role::Office);
        $this->section = $this->makeSection($year, 7, 'الف', ['class_teacher_id' => $staff->id]);
        $this->otherSection = $this->makeSection($year, 9);

        $enroll = app(EnrollmentService::class);
        $this->often = $enroll->enroll(Student::factory()->create(['first_name' => 'غایب‌زیاد']), $this->section, '2026-03-21');
        $this->good = $enroll->enroll(Student::factory()->create(['first_name' => 'منظم']), $this->section, '2026-03-21');
        $stranger = $enroll->enroll(Student::factory()->create(['first_name' => 'بیگانه']), $this->otherSection, '2026-03-21');

        // عقرب ۱۴۰۵: ۱ عقرب = 2026-10-23. پنج روز: دو روز غیرحاضر، یک رخصت
        $service = app(AttendanceService::class);
        $days = ['2026-10-24' => 'absent', '2026-10-25' => 'absent', '2026-10-26' => 'excused', '2026-10-27' => 'present', '2026-10-28' => 'late'];
        foreach ($days as $date => $status) {
            $day = CarbonImmutable::parse($date);
            $service->saveSection($this->section, $day, [$this->often->id => $status, $this->good->id => 'present'], [], $this->office);
            $service->saveSection($this->otherSection, $day, [$stranger->id => 'absent'], [], $this->office);
        }
    }

    public function test_monthly_grid_shows_codes_and_totals(): void
    {
        $response = $this->actingAs($this->teacher)->get(route('attendance.monthly.index', ['section' => $this->section->id, 'month' => '1405-08']))
            ->assertOk()->assertSee('غایب‌زیاد')->assertSee('منظم')->assertSee('عقرب ۱۴۰۵');

        $html = $response->getContent();
        $this->assertSame(2, substr_count($html, 'att-mark-absent'));
        $this->assertSame(1, substr_count($html, 'att-mark-excused'));
        $this->assertStringContainsString('att-holiday', $html); // جمعه‌ها

        $this->actingAs($this->teacher)->get(route('attendance.monthly.index', ['section' => $this->section->id, 'month' => '1405-08', 'print' => 1]))
            ->assertOk()->assertSee('print-landscape', false)->assertSee(__('app.signature_teacher'));
    }

    public function test_teacher_cannot_open_report_of_other_section(): void
    {
        $this->actingAs($this->teacher)->get(route('attendance.monthly.index', ['section' => $this->otherSection->id]))->assertForbidden();
        $this->actingAs($this->teacher)->get(route('attendance.monthly.export', ['section' => $this->otherSection->id]))->assertForbidden();
    }

    public function test_summary_of_sections_with_percent(): void
    {
        $this->actingAs($this->office)->get(route('attendance.monthly.index', ['month' => '1405-08']))
            ->assertOk()
            ->assertSee($this->section->label)
            ->assertSee($this->otherSection->label)
            ->assertSee('۷۰٪')   // ۷ حاضر/تأخیر از ۱۰
            ->assertSee('۰٪');

        // معلم فقط بخش خودش را در خلاصه می‌بیند
        $this->actingAs($this->teacher)->get(route('attendance.monthly.index', ['month' => '1405-08']))
            ->assertOk()->assertSee($this->section->label)->assertDontSee($this->otherSection->label);
    }

    public function test_monthly_csv_exports(): void
    {
        $csv = $this->actingAs($this->office)->get(route('attendance.monthly.export', ['section' => $this->section->id, 'month' => '1405-08']))
            ->assertOk()->streamedContent();
        $this->assertStringStartsWith("\xEF\xBB\xBF", $csv);
        $lines = array_values(array_filter(explode("\n", trim($csv))));
        $this->assertCount(3, $lines); // سربرگ + دو دانش‌آموز
        $this->assertStringContainsString('غایب‌زیاد', $lines[1]);
        $this->assertStringContainsString(',غ,غ,ر,ح,ت,', $lines[1]);

        $summary = $this->actingAs($this->office)->get(route('attendance.monthly.export', ['month' => '1405-08']))->assertOk()->streamedContent();
        $this->assertStringContainsString('70', $summary);
    }

    public function test_absentees_list_uses_threshold_from_settings(): void
    {
        Setting::put('absence_alert_days', '2');

        $this->actingAs($this->office)->get(route('attendance.absentees.index'))
            ->assertOk()->assertSee('غایب‌زیاد')->assertSee('بیگانه')->assertDontSee('منظم');

        $this->actingAs($this->office)->get(route('attendance.absentees.index', ['min' => 6]))
            ->assertOk()->assertSee(__('attendance.absentees_empty'));

        // معلم فقط بخش خودش
        $this->actingAs($this->teacher)->get(route('attendance.absentees.index'))
            ->assertOk()->assertSee('غایب‌زیاد')->assertDontSee('بیگانه');
    }

    public function test_deprivation_flag_and_csv(): void
    {
        Setting::put('absence_alert_days', '1');
        Setting::put('deprivation_absence_days', '3');

        $this->actingAs($this->office)->get(route('attendance.absentees.index'))
            ->assertOk()->assertSee(__('attendance.deprived'));   // بیگانه: ۵ روز غیرحاضر > ۳

        $csv = $this->actingAs($this->office)->get(route('attendance.absentees.export'))->assertOk()->streamedContent();
        $this->assertStringContainsString('بیگانه', $csv);
        $this->assertStringContainsString('غایب‌زیاد', $csv);
    }

    public function test_student_profile_shows_attendance_tab(): void
    {
        $this->actingAs($this->office)->get(route('students.show', $this->often->student))
            ->assertOk()
            ->assertSee(__('attendance.student_summary', ['year' => '۱۴۰۵']))
            ->assertSee('غیرحاضر: ۲')
            ->assertSee('رخصت: ۱')
            ->assertSee('۴۰٪');   // حاضر ۱ + تأخیر ۱ از ۵ روز

        $this->actingAs($this->teacher)->get(route('students.show', $this->good->student))
            ->assertOk()->assertSee('حاضر: ۵');
    }
}
