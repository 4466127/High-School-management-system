<?php

namespace Tests\Feature\Courses;

use App\Enums\CourseStatus;
use App\Enums\ParticipantStatus;
use App\Enums\Role;
use App\Models\ActivityLog;
use App\Models\Course;
use App\Models\CourseLedgerEntry;
use App\Models\CourseParticipant;
use App\Models\Permission;
use App\Services\CourseLedgerService;

/** کورس، گروه‌ها، اشتراک‌کنندگان و دسترسی‌ها. */
class CourseManagementTest extends CourseTestCase
{
    public function test_manager_creates_and_edits_course(): void
    {
        $this->actingAs($this->manager)->get(route('courses.list.create'))->assertOk();

        $this->actingAs($this->manager)->post(route('courses.list.store'), [
            'name' => 'کورس کامپیوتر', 'field' => 'کامپیوتر', 'season' => 'school_year',
            'start_date' => '۱۴۰۵/۰۸/۲۰', 'end_date' => '۱۴۰۵/۰۸/۱۰', 'status' => 'planned',
        ])->assertSessionHasErrors('end_date');

        $this->actingAs($this->manager)->post(route('courses.list.store'), [
            'name' => 'کورس کامپیوتر', 'field' => 'کامپیوتر', 'season' => 'school_year',
            'start_date' => '۱۴۰۵/۰۸/۲۰', 'end_date' => '', 'status' => 'planned',
        ])->assertSessionHasNoErrors()->assertRedirect();

        $course = Course::latest('id')->firstOrFail();
        $this->assertSame('2026-11-11', $course->start_date->toDateString());
        $this->assertNull($course->end_date);
        $this->assertTrue(ActivityLog::where('action', 'course.created')->exists());

        $this->actingAs($this->manager)->put(route('courses.list.update', $course), [
            'name' => 'کورس کامپیوتر', 'field' => 'کامپیوتر', 'season' => 'winter', 'start_date' => '۱۴۰۵/۰۸/۲۰', 'status' => 'active',
        ])->assertRedirect(route('courses.list.show', $course));
        $this->assertSame(CourseStatus::Active, $course->fresh()->status);
        $this->assertTrue(ActivityLog::where('action', 'course.updated')->exists());
    }

    public function test_groups_are_added_and_edited(): void
    {
        $this->actingAs($this->manager)->post(route('courses.list.groups.store', $this->course), ['name' => 'سطح مبتدی', 'shift' => 'morning'])
            ->assertSessionHasErrors('name'); // نام تکراری در همین کورس

        $this->actingAs($this->manager)->post(route('courses.list.groups.store', $this->course), [
            'name' => 'سطح پیشرفته', 'shift' => 'afternoon', 'staff_id' => $this->teacherStaff->id, 'schedule' => 'پنجشنبه، ۸ تا ۱۲',
        ])->assertSessionHasNoErrors();
        $group = $this->course->groups()->where('name', 'سطح پیشرفته')->firstOrFail();
        $this->assertTrue($group->is_active);

        $this->actingAs($this->manager)->put(route('courses.list.groups.update', $group), [
            'name' => 'سطح پیشرفته', 'shift' => 'afternoon', 'staff_id' => '', 'is_active' => '0',
        ])->assertSessionHasNoErrors();
        $this->assertFalse($group->fresh()->is_active);
        $this->assertNull($group->fresh()->staff_id);
    }

    public function test_school_students_and_outsiders_join_without_duplicates(): void
    {
        $this->actingAs($this->manager)->get(route('courses.list.show', [$this->course, 'pick' => $this->section->id]))
            ->assertOk()->assertSee('کورسیاول')->assertSee(__('courses.add_students'));

        $this->addStudents($this->morning, [0, 1])->assertSessionHas('success');
        // شاگرد دوم در گروه دیگر همین کورس دوباره شامل نمی‌شود
        $this->addStudents($this->afternoon, [1, 2])->assertSessionHas('success', __('courses.students_added', ['added' => '۱', 'skipped' => '۱']));
        $this->assertSame(3, CourseParticipant::count());

        $this->actingAs($this->manager)->post(route('courses.list.participants.external', $this->course), [
            'course_group_id' => $this->afternoon->id, 'name' => 'فریدون رحیمی', 'father_name' => 'کریم', 'phone' => '0700123456', 'joined_on' => '۱۴۰۵/۰۸/۲۰',
        ])->assertSessionHasNoErrors();

        // 2.1.0: فیس اختیاری ← سطر «فیس اشتراک» در صندوق کورس
        $this->actingAs($this->manager)->post(route('courses.list.participants.external', $this->course), [
            'course_group_id' => $this->afternoon->id, 'name' => 'نسرین احمدی', 'father_name' => 'احمد', 'joined_on' => '۱۴۰۵/۰۸/۲۱', 'fee' => '۵۰۰',
        ])->assertSessionHasNoErrors()->assertSessionHas('success');
        $fee = CourseLedgerEntry::where('entry_type', 'fee')->sole();
        $this->assertSame('500.00', $fee->amount);
        $this->assertSame('نسرین احمدی '.__('library.child_of').' احمد', $fee->donor_name);
        $this->assertSame(50_000, app(CourseLedgerService::class)->summary($this->course)['fees']);
        // کسی که دفتر مالی کورس ندارد فیس ثبت نمی‌کند (شخص هم ثبت نمی‌شود)
        $principalLike = $this->userWithRole(Role::Teacher);
        $principalLike->permissions()->attach(Permission::where('key', 'courses.manage')->value('id'));
        $this->actingAs($principalLike)->post(route('courses.list.participants.external', $this->course), [
            'course_group_id' => $this->afternoon->id, 'name' => 'بی دسترسی', 'joined_on' => '۱۴۰۵/۰۸/۲۱', 'fee' => '100',
        ])->assertSessionHasErrors('fee');
        $this->assertSame(0, CourseParticipant::where('name', 'بی دسترسی')->count());
        $this->actingAs($this->manager)->get(route('courses.ledger.show', $this->course))->assertOk()->assertSee(__('courses.sum_fees'))->assertSee('نسرین احمدی');

        $html = $this->actingAs($this->manager)->get(route('courses.list.show', $this->course))->assertOk()->getContent();
        $this->assertStringContainsString('کورسیاول', $html);
        $this->assertStringContainsString('فریدون رحیمی', $html);
        $this->assertStringContainsString(__('courses.external'), $html);

        // انتقال به گروه دیگر و تغییر وضعیت
        $p = CourseParticipant::where('student_id', $this->enrollments[0]->student_id)->sole();
        $this->actingAs($this->manager)->put(route('courses.list.participants.update', $p), [
            'course_group_id' => $this->afternoon->id, 'status' => 'left', 'note' => 'به ولایت دیگر رفت',
        ])->assertSessionHasNoErrors();
        $this->assertSame($this->afternoon->id, $p->fresh()->course_group_id);
        $this->assertSame(ParticipantStatus::Left, $p->fresh()->status);
        $this->assertTrue(ActivityLog::where('action', 'course.participant_updated')->exists());
    }

    public function test_access_by_role(): void
    {
        $other = Course::create(['name' => 'کورس ریاضی', 'field' => 'ریاضی', 'season' => 'winter', 'start_date' => '2026-11-01', 'status' => 'active']);

        // معلم فقط کورس خودش
        $this->actingAs($this->teacher)->get(route('courses.list.index'))->assertOk()->assertSee($this->course->name)->assertDontSee('کورس ریاضی');
        $this->actingAs($this->teacher)->get(route('courses.list.show', $other))->assertForbidden();
        $this->actingAs($this->teacher)->get(route('courses.list.show', $this->course))->assertOk()->assertDontSee(__('courses.open_ledger'));
        $this->actingAs($this->teacher)->get(route('courses.list.create'))->assertForbidden();

        // حسابدار همه را می‌بیند و دفتر مالی دارد، ولی مدیریت نه
        $this->actingAs($this->accountant)->get(route('courses.list.index'))->assertOk()->assertSee('کورس ریاضی');
        $this->actingAs($this->accountant)->get(route('courses.list.show', $this->course))->assertOk()->assertSee(__('courses.open_ledger'));
        $this->actingAs($this->accountant)->post(route('courses.list.groups.store', $this->course), ['name' => 'x', 'shift' => 'morning'])->assertForbidden();

        // مسئول اداری بدون مجوز کورس
        $this->actingAs($this->userWithRole(Role::Office))->get(route('courses.list.index'))->assertForbidden();
    }
}
