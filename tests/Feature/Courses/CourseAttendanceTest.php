<?php

namespace Tests\Feature\Courses;

use App\Enums\AttendanceStatus;
use App\Models\ActivityLog;
use App\Models\CourseAttendance;
use App\Models\CourseParticipant;
use App\Models\CourseSession;

/** حاضری کورس: معلم گروه خودش و فقط امروز؛ مسئول کورس‌ها هر روز. */
class CourseAttendanceTest extends CourseTestCase
{
    private function payload(string $date, array $statuses = []): array
    {
        $status = [];
        foreach (CourseParticipant::where('course_group_id', $this->morning->id)->where('status', 'active')->pluck('id') as $i => $id) {
            $status[$id] = $statuses[$i] ?? 'present';
        }

        return ['date' => $date, 'status' => $status, 'note' => []];
    }

    public function test_teacher_records_own_group_today(): void
    {
        $this->addStudents($this->morning, [0, 1, 2]);

        $this->actingAs($this->teacher)->get(route('courses.attendance.index'))->assertOk()->assertSee('سطح مبتدی')->assertDontSee('سطح متوسط');
        $this->actingAs($this->teacher)->get(route('courses.attendance.show', $this->morning))->assertOk()->assertSee('کورسیاول');

        $this->actingAs($this->teacher)->post(route('courses.attendance.store', $this->morning), $this->payload('2026-11-16', ['present', 'absent', 'sick']))
            ->assertSessionHas('success', __('courses.attendance_saved'));

        $session = CourseSession::sole();
        $this->assertSame(3, $session->records()->count());
        $this->assertSame(1, CourseAttendance::where('status', AttendanceStatus::Absent)->count());
        $this->assertTrue(ActivityLog::where('action', 'course.attendance_taken')->exists());

        // شمار حاضری در صفحه‌ی کورس
        $this->actingAs($this->manager)->get(route('courses.list.show', $this->course))->assertOk();
    }

    public function test_teacher_cannot_record_other_group_or_past_days(): void
    {
        $this->addStudents($this->morning, [0]);
        $this->addStudents($this->afternoon, [1]);

        $this->actingAs($this->teacher)->get(route('courses.attendance.show', $this->afternoon))->assertForbidden();
        $this->actingAs($this->teacher)->post(route('courses.attendance.store', $this->morning), $this->payload('2026-11-15'))->assertForbidden();
        $this->actingAs($this->teacher)->post(route('courses.attendance.store', $this->morning), $this->payload('2026-11-17'))->assertForbidden();
        $this->actingAs($this->teacher)->get(route('courses.attendance.show', [$this->morning, 'date' => '2026-11-15']))
            ->assertOk()->assertSee(__('courses.block_only_today'));
        $this->assertSame(0, CourseSession::count());
    }

    public function test_manager_corrects_past_day_with_log(): void
    {
        $this->addStudents($this->morning, [0, 1]);
        $this->actingAs($this->manager)->post(route('courses.attendance.store', $this->morning), $this->payload('2026-11-14'))->assertSessionHas('success');
        $this->actingAs($this->manager)->post(route('courses.attendance.store', $this->morning), $this->payload('2026-11-14', ['present', 'excused']))
            ->assertSessionHas('success', __('courses.attendance_corrected', ['count' => 1]));

        $this->assertSame(1, CourseSession::count());
        $this->assertSame(1, CourseAttendance::where('status', AttendanceStatus::Excused)->count());
        $this->assertTrue(ActivityLog::where('action', 'course.attendance_corrected')->exists());

        // پیش از شروع کورس بسته است
        $this->actingAs($this->manager)->post(route('courses.attendance.store', $this->morning), $this->payload('2026-10-20'))->assertForbidden();
    }

    public function test_incomplete_sheet_is_rejected(): void
    {
        $this->addStudents($this->morning, [0, 1]);
        $payload = $this->payload('2026-11-16');
        array_pop($payload['status']);

        $this->actingAs($this->teacher)->post(route('courses.attendance.store', $this->morning), $payload)->assertSessionHasErrors('status');
        $this->assertSame(0, CourseAttendance::count());
    }
}
