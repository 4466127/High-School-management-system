<?php

namespace Tests\Feature\Exams;

use App\Enums\ExamStatus;
use App\Enums\Role;
use App\Models\ActivityLog;
use App\Models\Exam;
use App\Models\ExamType;

/** تعریف امتحان، وضعیت‌ها، انواع امتحان. */
class ExamManagementTest extends ExamTestCase
{
    public function test_principal_defines_an_exam_with_defaults_from_its_type(): void
    {
        $type = ExamType::create(['name' => 'سالانه‌ی تست', 'default_max_mark' => 50, 'default_weight' => 50, 'counts_in_final' => true, 'sort_order' => 9]);

        $this->actingAs($this->principal)->get(route('exams.manage.index'))->assertOk()->assertSee('امتحان چهارونیم‌ماهه');
        $this->actingAs($this->principal)->post(route('exams.manage.store'), [
            '_modal' => 'examCreate', 'exam_type_id' => $type->id, 'name' => 'سالانه‌ی آزمایشی', 'start_date' => '۱۴۰۵/۰۹/۱۰', 'end_date' => '1405/09/20',
        ])->assertRedirect(route('exams.manage.index'));

        $exam = Exam::where('name', 'سالانه‌ی آزمایشی')->sole();
        $this->assertSame('50.00', $exam->max_mark);
        $this->assertSame('50.00', $exam->weight);
        $this->assertTrue($exam->counts_in_final);
        $this->assertSame(ExamStatus::Draft, $exam->status);
        $this->assertSame('2026-12-01', $exam->start_date->format('Y-m-d'));
        $this->assertTrue(ActivityLog::where('action', 'exam.created')->exists());
    }

    public function test_exam_values_are_validated(): void
    {
        $monthly = ExamType::where('counts_in_final', false)->firstOrFail();

        $this->actingAs($this->principal)->post(route('exams.manage.store'), [
            'exam_type_id' => $monthly->id, 'name' => 'امتحان سالانه', 'counts_in_final' => '1',
            'start_date' => '1405/09/20', 'end_date' => '1405/09/10',
        ])->assertSessionHasErrors(['name', 'end_date']);
    }

    /** وزن و دوره پرسیده نمی‌شود: وزن همیشه = نمره‌ی کامل (ورودی کهنه نادیده گرفته می‌شود) */
    public function test_weight_follows_max_mark_and_term_is_not_asked(): void
    {
        $monthly = ExamType::where('counts_in_final', false)->firstOrFail();

        $this->actingAs($this->principal)->get(route('exams.manage.index'))
            ->assertOk()->assertDontSee('name="weight"', false)->assertDontSee('name="academic_term_id"', false)->assertDontSee('name="default_weight"', false);

        $this->actingAs($this->principal)->post(route('exams.manage.store'), [
            'exam_type_id' => $monthly->id, 'name' => 'ماهوار حوت', 'max_mark' => '25', 'counts_in_final' => '1', 'weight' => '0',
        ])->assertSessionHasNoErrors();
        $exam = Exam::where('name', 'ماهوار حوت')->sole();
        $this->assertSame('25.00', $exam->weight);
        $this->assertTrue($exam->counts_in_final);
        $this->assertNull($exam->academic_term_id);
    }

    public function test_only_exam_managers_can_define_exams(): void
    {
        foreach ([Role::Teacher, Role::Office, Role::Accountant] as $role) {
            $this->actingAs($this->userWithRole($role))->get(route('exams.manage.index'))->assertForbidden();
        }
        $this->actingAs($this->teacher)->patch(route('exams.manage.status', $this->mid), ['status' => 'locked'])->assertForbidden();
    }

    public function test_status_changes_are_logged(): void
    {
        $this->actingAs($this->principal)->patch(route('exams.manage.status', $this->mid), ['status' => 'locked'])->assertRedirect();
        $this->assertSame(ExamStatus::Locked, $this->mid->fresh()->status);

        $log = ActivityLog::where('action', 'exam.status_changed')->sole();
        $this->assertSame('باز (ثبت نمره)', $log->old_values['status']);
        $this->assertSame('قفل', $log->new_values['status']);
    }

    public function test_exam_with_marks_cannot_be_deleted_or_shrunk_below_marks(): void
    {
        $this->enterMarks($this->mid, $this->math, [36, 10, 5])->assertRedirect();

        $this->actingAs($this->principal)->delete(route('exams.manage.destroy', $this->mid))->assertSessionHas('error', __('exams.exam_has_marks'));
        $this->assertNotNull($this->mid->fresh());

        $this->actingAs($this->principal)->put(route('exams.manage.update', $this->mid), [
            'exam_type_id' => $this->mid->exam_type_id, 'name' => $this->mid->name, 'max_mark' => '30', 'counts_in_final' => '1',
        ])->assertSessionHas('error');
        $this->assertSame('40.00', $this->mid->fresh()->max_mark);

        $empty = $this->makeExam('خالی', 20, 0, 'draft', false);
        $this->actingAs($this->principal)->delete(route('exams.manage.destroy', $empty))->assertRedirect();
        $this->assertNull($empty->fresh());
    }

    public function test_exam_types_can_be_added_and_edited(): void
    {
        $this->actingAs($this->principal)->post(route('exams.manage.types.store'), [
            '_modal' => 'typeCreate', 'name' => 'آزمون صنفی', 'default_max_mark' => '10', 'counts_in_final' => '0', 'sort_order' => '5', 'is_active' => '1',
        ])->assertRedirect();
        $type = ExamType::where('name', 'آزمون صنفی')->sole();
        $this->assertFalse($type->counts_in_final);
        $this->assertSame('10.00', $type->default_weight);

        $this->actingAs($this->principal)->put(route('exams.manage.types.update', $type), [
            'name' => 'آزمون صنفی', 'default_max_mark' => '15', 'counts_in_final' => '1', 'sort_order' => '5', 'is_active' => '0',
        ])->assertRedirect();
        $this->assertFalse($type->fresh()->is_active);
        $this->assertSame('15.00', $type->fresh()->default_weight);
        $this->assertTrue(ActivityLog::where('action', 'exam_type.updated')->exists());
    }
}
