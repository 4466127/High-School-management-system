<?php

namespace Tests\Feature\Attendance;

use App\Enums\AttendanceStatus;
use App\Enums\Role;
use App\Models\ActivityLog;
use App\Models\Staff;
use App\Models\StaffAttendance;
use Carbon\Carbon;
use Carbon\CarbonImmutable;
use Tests\TestCase;

/** حاضری ساده‌ی کارکنان. */
class StaffAttendanceTest extends TestCase
{
    private Staff $working;

    private Staff $onLeave;

    protected function setUp(): void
    {
        parent::setUp();
        Carbon::setTestNow('2026-11-16 09:00:00');
        CarbonImmutable::setTestNow('2026-11-16 09:00:00');

        $this->working = Staff::factory()->create(['first_name' => 'کارمندفعال']);
        $this->onLeave = Staff::factory()->create(['first_name' => 'کارمندرخصت']);
        $this->onLeave->forceFill(['employment_status' => 'on_leave'])->save();
        Staff::factory()->create(['first_name' => 'کارمندرفته'])->forceFill(['employment_status' => 'left'])->save();
    }

    public function test_office_records_staff_attendance_and_corrections_are_logged(): void
    {
        $office = $this->userWithRole(Role::Office);

        $this->actingAs($office)->get(route('attendance.staff.index'))
            ->assertOk()->assertSee('کارمندفعال')->assertSee('کارمندرخصت')->assertDontSee('کارمندرفته')
            ->assertSee('id="ss-'.$this->onLeave->id.'-excused" value="excused" autocomplete="off" checked', false);

        $statuses = [$this->working->id => 'present', $this->onLeave->id => 'excused'];
        $this->actingAs($office)->post(route('attendance.staff.store'), ['date' => '2026-11-16', 'status' => $statuses])
            ->assertRedirect(route('attendance.staff.index', ['date' => '2026-11-16']));
        $this->assertSame(2, StaffAttendance::count());
        $this->assertSame(1, ActivityLog::where('action', 'attendance.staff_taken')->count());

        $statuses[$this->working->id] = 'late';
        $this->actingAs($office)->post(route('attendance.staff.store'), ['date' => '2026-11-16', 'status' => $statuses, 'note' => [$this->working->id => 'ترافیک']]);
        $this->assertSame(2, StaffAttendance::count());
        $this->assertSame(AttendanceStatus::Late, StaffAttendance::where('staff_id', $this->working->id)->value('status'));

        $log = ActivityLog::where('action', 'attendance.staff_corrected')->sole();
        $this->assertSame('حاضر', $log->old_values[$this->working->full_name]);
        $this->assertSame('تأخیر (ترافیک)', $log->new_values[$this->working->full_name]);
    }

    public function test_future_day_is_refused(): void
    {
        $this->actingAs($this->userWithRole(Role::Office))
            ->post(route('attendance.staff.store'), ['date' => '2026-11-18', 'status' => [$this->working->id => 'present', $this->onLeave->id => 'present']])
            ->assertSessionHas('error', __('attendance.blocked_future'));
        $this->assertSame(0, StaffAttendance::count());
    }

    public function test_teacher_cannot_open_staff_attendance(): void
    {
        $teacher = $this->userWithRole(Role::Teacher);
        $this->actingAs($teacher)->get(route('attendance.staff.index'))->assertForbidden();
        $this->actingAs($teacher)->post(route('attendance.staff.store'), ['date' => '2026-11-16', 'status' => []])->assertForbidden();
        $this->actingAs($teacher)->get(route('attendance.staff.monthly'))->assertForbidden();
    }

    public function test_monthly_summary_and_csv(): void
    {
        $office = $this->userWithRole(Role::Office);
        foreach (['2026-11-14' => 'absent', '2026-11-15' => 'present', '2026-11-16' => 'absent'] as $date => $status) {
            $this->actingAs($office)->post(route('attendance.staff.store'), [
                'date' => $date, 'status' => [$this->working->id => $status, $this->onLeave->id => 'excused'],
            ])->assertRedirect();
        }

        $this->actingAs($office)->get(route('attendance.staff.monthly', ['month' => '1405-08']))
            ->assertOk()->assertSee('کارمندفعال')->assertSee('عقرب ۱۴۰۵');

        $csv = $this->actingAs($office)->get(route('attendance.staff.monthly.export', ['month' => '1405-08']))->assertOk()->streamedContent();
        $line = collect(explode("\n", $csv))->first(fn ($l) => str_contains($l, 'کارمندفعال'));
        $this->assertStringEndsWith(',1,0,2,0,0', trim($line));   // حاضر، تأخیر، غیرحاضر، رخصت، مریض
    }
}
