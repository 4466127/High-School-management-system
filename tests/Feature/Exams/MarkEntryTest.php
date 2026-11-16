<?php

namespace Tests\Feature\Exams;

use App\Enums\Role;
use App\Models\ActivityLog;
use App\Models\Mark;
use App\Models\Subject;

/** ثبت نمره: معلم فقط مضمون خودش، فقط امتحان باز؛ اصلاح با ردپا. */
class MarkEntryTest extends ExamTestCase
{
    public function test_teacher_enters_marks_for_own_subject(): void
    {
        $this->actingAs($this->teacher)->get(route('exams.marks.index', ['exam' => $this->mid->id]))
            ->assertOk()->assertSee('ریاضی')->assertSee(route('exams.marks.show', [$this->mid, $this->section, $this->math]))
            ->assertDontSee(route('exams.marks.show', [$this->mid, $this->section, $this->dari]));

        $this->actingAs($this->teacher)->get(route('exams.marks.show', [$this->mid, $this->section, $this->math]))
            ->assertOk()->assertSee('شاگرداول')->assertSee(__('exams.save_marks'))->assertDontSee('بیگانه');

        $this->enterMarks($this->mid, $this->math, ['۳۶٫۵', 'absent', ''], $this->teacher)
            ->assertRedirect(route('exams.marks.show', [$this->mid, $this->section, $this->math]))
            ->assertSessionHas('success');

        $this->assertSame('36.50', Mark::where('enrollment_id', $this->students[0]->id)->value('mark'));
        $absent = Mark::where('enrollment_id', $this->students[1]->id)->sole();
        $this->assertTrue($absent->is_absent);
        $this->assertNull($absent->mark);
        $this->assertFalse(Mark::where('enrollment_id', $this->students[2]->id)->exists());   // خالی ← ثبت نمی‌شود

        $log = ActivityLog::where('action', 'marks.entered')->sole();
        $this->assertSame(2, $log->new_values['count']);
        $this->assertSame($this->teacher->id, $log->user_id);
    }

    public function test_corrections_are_logged_with_old_and_new_values(): void
    {
        $this->enterMarks($this->mid, $this->math, [30, 20, 10], $this->teacher);
        $this->enterMarks($this->mid, $this->math, [32, 20, ''], $this->teacher)->assertSessionHas('success');

        $this->assertSame(3, Mark::count());
        $this->assertNull(Mark::where('enrollment_id', $this->students[2]->id)->value('mark'));   // خالی کردن = اصلاح

        $log = ActivityLog::where('action', 'marks.updated')->sole();
        $first = '۱ — '.$this->students[0]->student->full_name;
        $third = '۳ — '.$this->students[2]->student->full_name;
        $this->assertSame('۳۰', $log->old_values[$first]);
        $this->assertSame('۳۲', $log->new_values[$first]);
        $this->assertSame('۱۰', $log->old_values[$third]);
        $this->assertSame('—', $log->new_values[$third]);
    }

    public function test_mark_above_max_or_not_a_number_is_rejected(): void
    {
        $this->enterMarks($this->mid, $this->math, [41, 'abc', -1], $this->teacher)
            ->assertSessionHasErrors([
                'marks.'.$this->students[0]->id => __('exams.mark_too_high', ['max' => '۴۰']),
                'marks.'.$this->students[1]->id,
                'marks.'.$this->students[2]->id,
            ]);
        $this->assertSame(0, Mark::count());
    }

    public function test_teacher_cannot_enter_marks_of_other_subjects_or_sections(): void
    {
        $this->actingAs($this->teacher)->get(route('exams.marks.show', [$this->mid, $this->section, $this->dari]))->assertForbidden();
        $this->enterMarks($this->mid, $this->dari, [30, 30, 30], $this->teacher)->assertForbidden();
        $this->actingAs($this->teacher)->get(route('exams.marks.show', [$this->mid, $this->otherSection, $this->math]))->assertForbidden();
        $this->assertSame(0, Mark::count());

        // مضمونی که در صنف نیست ← ۴۰۴
        $other = Subject::where('name', 'کیمیا')->firstOrFail();
        $this->actingAs($this->principal)->get(route('exams.marks.show', [$this->mid, $this->section, $other]))->assertNotFound();
    }

    public function test_draft_and_locked_exams_are_closed_to_teachers(): void
    {
        $this->mid->update(['status' => 'draft']);
        $this->actingAs($this->teacher)->get(route('exams.marks.show', [$this->mid, $this->section, $this->math]))
            ->assertOk()->assertSee(__('exams.blocked_draft'))->assertDontSee(__('exams.save_marks'));
        $this->enterMarks($this->mid, $this->math, [30, 30, 30], $this->teacher)->assertForbidden();
        $this->enterMarks($this->mid, $this->math, [30, 30, 30])->assertForbidden();   // پیش‌نویس برای مدیر هم

        $this->mid->update(['status' => 'locked']);
        $this->actingAs($this->teacher)->get(route('exams.marks.show', [$this->mid, $this->section, $this->math]))
            ->assertOk()->assertSee(__('exams.blocked_locked'));
        $this->enterMarks($this->mid, $this->math, [30, 30, 30], $this->teacher)->assertForbidden();

        // مدیر امتحان قفل را اصلاح می‌کند
        $this->enterMarks($this->mid, $this->math, [30, 30, 30])->assertRedirect()->assertSessionHas('success');
        $this->assertSame(3, Mark::count());
    }

    public function test_office_has_no_mark_entry(): void
    {
        $office = $this->userWithRole(Role::Office);
        $this->actingAs($office)->get(route('exams.marks.index'))->assertForbidden();
        $this->enterMarks($this->mid, $this->math, [30, 30, 30], $office)->assertForbidden();
    }
}
