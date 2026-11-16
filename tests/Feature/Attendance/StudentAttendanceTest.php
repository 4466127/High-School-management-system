<?php

namespace Tests\Feature\Attendance;

use App\Enums\AttendanceStatus;
use App\Enums\EnrollmentStatus;
use App\Enums\Role;
use App\Models\ActivityLog;
use App\Models\AttendanceRecord;
use App\Models\AttendanceSession;
use App\Models\Enrollment;
use App\Models\Section;
use App\Models\Staff;
use App\Models\Student;
use App\Models\User;
use App\Services\EnrollmentService;
use Carbon\Carbon;
use Carbon\CarbonImmutable;
use Illuminate\Database\UniqueConstraintViolationException;
use Tests\TestCase;

/** حاضری روزانه‌ی بخش‌ها: ثبت، جلوگیری از ثبت دوباره، اصلاح با ردپا، محدوده‌ی معلم. */
class StudentAttendanceTest extends TestCase
{
    private const TODAY = '2026-11-16';      // دوشنبه ۲۵ عقرب ۱۴۰۵

    private User $teacher;

    private Section $mySection;

    private Section $otherSection;

    /** @var list<Enrollment> */
    private array $mine = [];

    protected function setUp(): void
    {
        parent::setUp();
        Carbon::setTestNow(self::TODAY.' 09:00:00');
        CarbonImmutable::setTestNow(self::TODAY.' 09:00:00');

        $year = $this->makeYear();
        $staff = Staff::factory()->create();
        $this->teacher = $this->userWithRole(Role::Teacher, ['staff_id' => $staff->id]);
        $this->mySection = $this->makeSection($year, 7, 'الف', ['class_teacher_id' => $staff->id]);
        $this->otherSection = $this->makeSection($year, 8);

        $service = app(EnrollmentService::class);
        foreach (['اول', 'دوم', 'سوم'] as $name) {
            $this->mine[] = $service->enroll(Student::factory()->create(['first_name' => 'شاگرد'.$name]), $this->mySection, '2026-03-21');
        }
        $service->enroll(Student::factory()->create(['first_name' => 'بیگانه']), $this->otherSection, '2026-03-21');
    }

    private function statuses(array $overrides = []): array
    {
        $statuses = [];
        foreach ($this->mine as $enrollment) {
            $statuses[$enrollment->id] = 'present';
        }

        return array_replace($statuses, $overrides);
    }

    private function take(User $user, string $date = self::TODAY, array $overrides = [], array $notes = [], ?Section $section = null)
    {
        return $this->actingAs($user)->post(route('attendance.daily.store', $section ?? $this->mySection), [
            'date' => $date,
            'status' => $this->statuses($overrides),
            'note' => $notes,
        ]);
    }

    public function test_teacher_takes_todays_attendance_for_own_section(): void
    {
        $this->actingAs($this->teacher)->get(route('attendance.daily.show', $this->mySection))
            ->assertOk()->assertSee('شاگرداول')->assertSee(__('attendance.save_sheet'))->assertDontSee('بیگانه');

        $absent = $this->mine[1]->id;
        $this->take($this->teacher, overrides: [$absent => 'absent'], notes: [$absent => 'بدون اطلاع'])
            ->assertRedirect(route('attendance.daily.index', ['date' => self::TODAY]))
            ->assertSessionHas('success');

        $session = AttendanceSession::where('section_id', $this->mySection->id)->sole();
        $this->assertSame(self::TODAY, $session->date->format('Y-m-d'));
        $this->assertSame($this->teacher->id, $session->taken_by);
        $this->assertSame(3, $session->records()->count());

        $record = AttendanceRecord::where('enrollment_id', $absent)->sole();
        $this->assertSame(AttendanceStatus::Absent, $record->status);
        $this->assertSame('بدون اطلاع', $record->note);

        $log = ActivityLog::where('action', 'attendance.taken')->sole();
        $this->assertSame(2, $log->new_values['حاضر']);
        $this->assertSame(1, $log->new_values['غیرحاضر']);
    }

    public function test_same_day_cannot_be_recorded_twice_and_corrections_are_logged(): void
    {
        $this->take($this->teacher)->assertRedirect();
        $target = $this->mine[2];
        $this->take($this->teacher, overrides: [$target->id => 'sick'])->assertRedirect()->assertSessionHas('success', __('attendance.corrected', ['count' => '۱']));

        $this->assertSame(1, AttendanceSession::where('section_id', $this->mySection->id)->count());
        $this->assertSame(3, AttendanceRecord::count());
        $this->assertSame(AttendanceStatus::Sick, AttendanceRecord::where('enrollment_id', $target->id)->value('status'));

        $log = ActivityLog::where('action', 'attendance.corrected')->sole();
        $key = '۳ — '.$target->student->full_name;
        $this->assertSame('حاضر', $log->old_values[$key]);
        $this->assertSame('مریض', $log->new_values[$key]);
        $this->assertSame($this->teacher->id, AttendanceSession::first()->updated_by);

        // ذخیره‌ی بدون تغییر چیزی ثبت نمی‌کند
        $this->take($this->teacher, overrides: [$target->id => 'sick'])->assertSessionHas('success', __('attendance.no_changes'));
        $this->assertSame(1, ActivityLog::where('action', 'attendance.corrected')->count());

        // کلید یکتای پایگاه داده
        $this->expectException(UniqueConstraintViolationException::class);
        AttendanceSession::create(['section_id' => $this->mySection->id, 'date' => self::TODAY]);
    }

    public function test_teacher_cannot_touch_other_sections(): void
    {
        $this->actingAs($this->teacher)->get(route('attendance.daily.show', $this->otherSection))->assertForbidden();
        $this->take($this->teacher, section: $this->otherSection)->assertForbidden();
        $this->assertSame(0, AttendanceSession::count());
    }

    public function test_teacher_cannot_correct_past_days_but_office_can(): void
    {
        $yesterday = '2026-11-15';
        $this->actingAs($this->teacher)->get(route('attendance.daily.show', ['section' => $this->mySection, 'date' => $yesterday]))
            ->assertOk()->assertSee(__('attendance.blocked_past'))->assertDontSee(__('attendance.save_sheet'));
        $this->take($this->teacher, $yesterday)->assertForbidden();

        $office = $this->userWithRole(Role::Office);
        $this->take($office, $yesterday)->assertRedirect();
        $this->take($office, $yesterday, [$this->mine[0]->id => 'excused'], [$this->mine[0]->id => 'عروسی'])->assertRedirect();

        $log = ActivityLog::where('action', 'attendance.corrected')->sole();
        $this->assertSame($office->id, $log->user_id);
        $this->assertSame('رخصت (عروسی)', $log->new_values['۱ — '.$this->mine[0]->student->full_name]);

        // معلم حاضری ثبت‌شده‌ی دیروز را فقط می‌بیند
        $this->actingAs($this->teacher)->get(route('attendance.daily.show', ['section' => $this->mySection, 'date' => $yesterday]))
            ->assertOk()->assertSee('عروسی');
    }

    public function test_future_dates_and_dates_outside_the_year_are_refused(): void
    {
        $principal = $this->userWithRole(Role::Principal);
        $this->take($principal, '2026-11-17')->assertForbidden();
        $this->actingAs($principal)->get(route('attendance.daily.show', ['section' => $this->mySection, 'date' => '2026-11-17']))
            ->assertOk()->assertSee(__('attendance.blocked_future'));

        $this->take($principal, '2026-03-01')->assertSessionHas('error', __('attendance.blocked_out_of_year'));
        $this->assertSame(0, AttendanceSession::count());
    }

    public function test_closed_year_is_read_only(): void
    {
        $this->mySection->academicYear->update(['status' => 'closed']);
        $this->take($this->userWithRole(Role::Principal))->assertForbidden();
    }

    public function test_every_student_needs_a_valid_status(): void
    {
        $statuses = $this->statuses();
        array_pop($statuses);
        $this->actingAs($this->teacher)->post(route('attendance.daily.store', $this->mySection), ['date' => self::TODAY, 'status' => $statuses])
            ->assertSessionHasErrors(['status' => __('attendance.list_changed')]);
        $this->assertSame(0, AttendanceSession::count());

        $this->take($this->teacher, overrides: [$this->mine[0]->id => 'holiday'])->assertSessionHasErrors('status.'.$this->mine[0]->id);
        $this->assertSame(0, AttendanceRecord::count());
    }

    public function test_sheet_lists_only_students_enrolled_on_that_day(): void
    {
        $service = app(EnrollmentService::class);
        $late = $service->enroll(Student::factory()->create(['first_name' => 'تازه‌وارد']), $this->mySection, '2026-11-10');
        $service->leave($this->mine[2]->fresh(), EnrollmentStatus::Transferred, '2026-11-01', 'انتقال به ولایت دیگر');

        $office = $this->userWithRole(Role::Office);
        $this->actingAs($office)->get(route('attendance.daily.show', ['section' => $this->mySection, 'date' => '2026-10-20']))
            ->assertOk()->assertSee('شاگردسوم')->assertDontSee('تازه‌وارد');
        $this->actingAs($office)->get(route('attendance.daily.show', $this->mySection))
            ->assertOk()->assertDontSee('شاگردسوم')->assertSee('تازه‌وارد');

        $this->actingAs($office)->post(route('attendance.daily.store', $this->mySection), [
            'date' => self::TODAY,
            'status' => [$this->mine[0]->id => 'present', $this->mine[1]->id => 'late', $late->id => 'present'],
        ])->assertRedirect();
        $this->assertSame(3, AttendanceRecord::count());
        $this->assertFalse(AttendanceRecord::where('enrollment_id', $this->mine[2]->id)->exists());
    }

    public function test_daily_page_shows_only_own_sections_and_who_was_absent(): void
    {
        $this->take($this->teacher, overrides: [$this->mine[1]->id => 'absent'])->assertRedirect();

        $this->actingAs($this->teacher)->get(route('attendance.daily.index'))
            ->assertOk()
            ->assertSee(route('attendance.daily.show', ['section' => $this->mySection, 'date' => self::TODAY]))
            ->assertDontSee(route('attendance.daily.show', ['section' => $this->otherSection, 'date' => self::TODAY]))
            ->assertSee('شاگرددوم')
            ->assertSee(__('attendance.taken'));

        $this->actingAs($this->userWithRole(Role::Office))->get(route('attendance.daily.index', ['date' => '۱۴۰۵/۰۸/۲۵']))
            ->assertOk()
            ->assertSee(route('attendance.daily.show', ['section' => $this->otherSection, 'date' => self::TODAY]))
            ->assertSee(__('attendance.not_taken'));

        $this->actingAs($this->teacher)->get(route('attendance.daily.index', ['print' => 1]))->assertOk()->assertSee(__('attendance.daily_print_title'));
    }

    public function test_daily_csv_lists_non_present_students(): void
    {
        $this->take($this->teacher, overrides: [$this->mine[1]->id => 'absent'], notes: [$this->mine[1]->id => 'مریضی مادر'])->assertRedirect();

        $response = $this->actingAs($this->teacher)->get(route('attendance.daily.export', ['date' => self::TODAY]));
        $response->assertOk()->assertHeader('content-type', 'text/csv; charset=UTF-8');
        $csv = $response->streamedContent();
        $this->assertStringStartsWith("\xEF\xBB\xBF", $csv);
        $this->assertStringContainsString('شاگرددوم', $csv);
        $this->assertStringContainsString('مریضی مادر', $csv);
        $this->assertStringContainsString('1405/08/25', $csv);
        $this->assertStringNotContainsString('شاگرداول', $csv);
    }

    public function test_accountant_has_no_attendance_access(): void
    {
        $accountant = $this->userWithRole(Role::Accountant);
        $this->actingAs($accountant)->get(route('attendance.daily.index'))->assertForbidden();
        $this->actingAs($accountant)->get(route('attendance.monthly.index'))->assertForbidden();
        $this->take($accountant)->assertForbidden();
    }

    public function test_menu_links_follow_permissions(): void
    {
        $sidebar = fn (User $user) => $this->actingAs($user)->get('/')->assertOk()->getContent();

        $teacherMenu = $sidebar($this->teacher);
        $this->assertStringContainsString(route('attendance.daily.index'), $teacherMenu);
        $this->assertStringNotContainsString(route('attendance.staff.index'), $teacherMenu);

        $this->assertStringContainsString(route('attendance.staff.index'), $sidebar($this->userWithRole(Role::Office)));
    }

    public function test_section_page_links_to_attendance(): void
    {
        $this->actingAs($this->teacher)->get(route('academics.sections.show', $this->mySection))
            ->assertOk()->assertSee(route('attendance.daily.show', $this->mySection));
    }
}
