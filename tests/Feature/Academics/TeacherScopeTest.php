<?php

namespace Tests\Feature\Academics;

use App\Enums\Role;
use App\Models\Section;
use App\Models\Staff;
use App\Models\Student;
use App\Models\Subject;
use App\Models\User;
use App\Services\EnrollmentService;
use Tests\TestCase;

/** معلم فقط دانش‌آموزان و بخش‌هایی را می‌بیند که نگران صنف یا معلم مضمون آن است. */
class TeacherScopeTest extends TestCase
{
    private User $teacher;

    private Student $mine;

    private Student $other;

    private Section $mySection;

    private Section $otherSection;

    protected function setUp(): void
    {
        parent::setUp();
        $year = $this->makeYear();
        $staff = Staff::factory()->create();
        $this->teacher = $this->userWithRole(Role::Teacher, ['staff_id' => $staff->id]);

        $this->mySection = $this->makeSection($year, 7);
        $this->otherSection = $this->makeSection($year, 8);
        $this->mySection->assignments()->create(['subject_id' => Subject::first()->id, 'staff_id' => $staff->id]);

        $service = app(EnrollmentService::class);
        $this->mine = Student::factory()->create(['first_name' => 'شاگردمن']);
        $this->other = Student::factory()->create(['first_name' => 'شاگرددیگر']);
        $service->enroll($this->mine, $this->mySection);
        $service->enroll($this->other, $this->otherSection);
    }

    public function test_teacher_sees_only_students_of_own_sections(): void
    {
        $this->actingAs($this->teacher)->get('/students?status=all')
            ->assertOk()->assertSee('شاگردمن')->assertDontSee('شاگرددیگر');

        $this->actingAs($this->teacher)->get("/students/{$this->mine->id}")->assertOk();
        $this->actingAs($this->teacher)->get("/students/{$this->other->id}")->assertForbidden();
    }

    public function test_class_teacher_also_has_scope(): void
    {
        $staff = Staff::factory()->create();
        $classTeacher = $this->userWithRole(Role::Teacher, ['staff_id' => $staff->id]);
        $this->otherSection->update(['class_teacher_id' => $staff->id]);

        $this->actingAs($classTeacher)->get("/students/{$this->other->id}")->assertOk();
        $this->actingAs($classTeacher)->get("/students/{$this->mine->id}")->assertForbidden();
    }

    public function test_teacher_sees_only_own_sections(): void
    {
        $this->actingAs($this->teacher)->get('/academics/sections')
            ->assertOk()->assertSee(route('academics.sections.show', $this->mySection))->assertDontSee(route('academics.sections.show', $this->otherSection));

        $this->actingAs($this->teacher)->get("/academics/sections/{$this->mySection->id}")->assertOk()->assertSee('شاگردمن');
        $this->actingAs($this->teacher)->get("/academics/sections/{$this->otherSection->id}")->assertForbidden();
        $this->actingAs($this->teacher)->get("/academics/sections/{$this->mySection->id}/enroll")->assertForbidden();
    }

    public function test_teacher_without_staff_link_sees_no_students(): void
    {
        $unlinked = $this->userWithRole(Role::Teacher);

        $this->actingAs($unlinked)->get('/students?status=all')
            ->assertOk()->assertDontSee('شاگردمن')->assertSee(__('academics.teacher_not_linked'));
    }

    public function test_office_and_accountant_see_everyone(): void
    {
        foreach ([Role::Office, Role::Accountant] as $role) {
            $this->actingAs($this->userWithRole($role))->get('/students?status=all')->assertSee('شاگردمن')->assertSee('شاگرددیگر');
        }
    }

    public function test_section_filter_on_student_list(): void
    {
        $this->actingAs($this->userWithRole(Role::Office))->get("/students?status=all&section={$this->otherSection->id}")
            ->assertSee('شاگرددیگر')->assertDontSee('شاگردمن');
    }
}
