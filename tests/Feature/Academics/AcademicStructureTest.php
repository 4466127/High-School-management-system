<?php

namespace Tests\Feature\Academics;

use App\Enums\Role;
use App\Models\AcademicYear;
use App\Models\GradeLevel;
use App\Models\Section;
use App\Models\Staff;
use App\Models\Student;
use App\Models\Subject;
use App\Models\User;
use App\Services\EnrollmentService;
use Tests\TestCase;

class AcademicStructureTest extends TestCase
{
    private User $office;

    protected function setUp(): void
    {
        parent::setUp();
        $this->office = $this->userWithRole(Role::Office);
    }

    public function test_grades_one_to_twelve_and_subjects_are_seeded(): void
    {
        $this->assertSame(12, GradeLevel::count());
        $this->assertSame('صنف هفتم', GradeLevel::where('sort_order', 7)->value('name'));
        $this->assertSame('صنف هشتم', GradeLevel::where('sort_order', 7)->first()->next()->name);
        $this->assertNull(GradeLevel::where('sort_order', 12)->first()->next());
        $this->assertTrue(Subject::where('name', 'ریاضی')->exists());
    }

    public function test_first_year_becomes_current_and_dates_are_jalali(): void
    {
        $this->actingAs($this->office)->post('/academics/years', [
            'name' => '1405', 'start_date' => '1405/01/01', 'end_date' => '1405/09/30',
        ])->assertRedirect();

        $year = AcademicYear::where('name', '1405')->firstOrFail();
        $this->assertTrue($year->is_current);
        $this->assertSame('2026-03-21', $year->start_date->toDateString());
        $this->assertDatabaseHas('activity_logs', ['action' => 'academic_year.created', 'subject_id' => $year->id]);
    }

    public function test_year_validation(): void
    {
        $this->makeYear('1405');

        $this->actingAs($this->office)->post('/academics/years', [
            'name' => '1405', 'start_date' => '1406/01/01', 'end_date' => '1405/12/01',
        ])->assertSessionHasErrors(['name', 'end_date']);
    }

    public function test_new_year_copies_sections_and_teachers_and_only_one_is_current(): void
    {
        $old = $this->makeYear('1405');
        $teacher = Staff::factory()->create();
        $section = $this->makeSection($old, 7, 'الف', ['class_teacher_id' => $teacher->id]);
        $section->assignments()->create(['subject_id' => Subject::first()->id, 'staff_id' => $teacher->id]);

        $this->actingAs($this->office)->post('/academics/years', [
            'name' => '1406', 'start_date' => '1406/01/01', 'end_date' => '1406/09/30',
            'copy_from' => $old->id, 'copy_assignments' => '1',
        ])->assertRedirect();

        $new = AcademicYear::where('name', '1406')->firstOrFail();
        $this->assertFalse($new->is_current, 'A new year does not replace the current one automatically');
        $copy = $new->sections()->firstOrFail();
        $this->assertSame([$section->grade_level_id, 'الف', $teacher->id], [$copy->grade_level_id, $copy->name, $copy->class_teacher_id]);
        $this->assertSame(1, $copy->assignments()->count());

        $this->actingAs($this->office)->patch("/academics/years/{$new->id}/current")->assertRedirect();
        $this->assertSame(['1406'], AcademicYear::where('is_current', true)->pluck('name')->all());
    }

    public function test_terms_are_managed_inside_a_year(): void
    {
        $year = $this->makeYear();

        $this->actingAs($this->office)->post("/academics/years/{$year->id}/terms", [
            'name' => 'چهارونیم‌ماهه‌ی اول', 'sort_order' => 1, 'start_date' => '1405/01/01', 'end_date' => '1405/05/15',
        ])->assertRedirect();

        $term = $year->terms()->firstOrFail();
        $this->assertSame('2026-08-06', $term->end_date->toDateString());

        $other = $this->makeYear('1406', '2027-03-21', '2027-12-21', false);
        $this->actingAs($this->office)->delete("/academics/years/{$other->id}/terms/{$term->id}")->assertNotFound();
        $this->actingAs($this->office)->delete("/academics/years/{$year->id}/terms/{$term->id}")->assertRedirect();
        $this->assertModelMissing($term);
    }

    public function test_grade_subjects_with_report_card_order(): void
    {
        $grade = GradeLevel::where('sort_order', 7)->first();
        [$math, $dari] = [Subject::where('name', 'ریاضی')->first(), Subject::where('name', 'دری')->first()];

        $this->actingAs($this->office)->put("/academics/grades/{$grade->id}/subjects", [
            'selected' => [$math->id, $dari->id],
            'order' => [$math->id => 2, $dari->id => 1],
        ])->assertRedirect();

        $this->assertSame(['دری', 'ریاضی'], $grade->subjects()->pluck('name')->all());
        $this->assertDatabaseHas('activity_logs', ['action' => 'grade.subjects_updated', 'subject_id' => $grade->id]);
    }

    public function test_school_can_add_extra_grades_and_subjects(): void
    {
        $this->actingAs($this->office)->post('/academics/grades', ['name' => 'آمادگی', 'sort_order' => 0, 'is_active' => '1'])
            ->assertSessionHasErrors('sort_order');
        $this->actingAs($this->office)->post('/academics/grades', ['name' => 'صنف سیزدهم', 'sort_order' => 13, 'is_active' => '1'])->assertRedirect();
        $this->assertSame('صنف سیزدهم', GradeLevel::where('sort_order', 12)->first()->next()->name);

        $this->actingAs($this->office)->post('/academics/subjects', ['name' => 'ریاضی', 'is_active' => '1'])->assertSessionHasErrors('name');
        $this->actingAs($this->office)->post('/academics/subjects', ['name' => 'اقتصاد', 'code' => 'ECO', 'is_active' => '1'])->assertRedirect();
        $this->assertTrue(Subject::where('name', 'اقتصاد')->where('is_active', true)->exists());
    }

    public function test_sections_are_unique_per_year_grade_name_and_shift(): void
    {
        $year = $this->makeYear();
        $grade = GradeLevel::where('sort_order', 7)->value('id');
        $data = ['academic_year_id' => $year->id, 'grade_level_id' => $grade, 'name' => 'الف', 'shift' => 'morning'];

        $this->actingAs($this->office)->post('/academics/sections', $data)->assertRedirect();
        $this->actingAs($this->office)->post('/academics/sections', $data)->assertSessionHasErrors(['name' => __('academics.section_exists')]);
        $this->actingAs($this->office)->post('/academics/sections', ['shift' => 'afternoon'] + $data)->assertRedirect();

        $this->assertSame(2, Section::where('academic_year_id', $year->id)->count());
    }

    public function test_teacher_per_subject_and_class_teacher(): void
    {
        $year = $this->makeYear();
        $section = $this->makeSection($year);
        $math = Subject::where('name', 'ریاضی')->first();
        $section->gradeLevel->subjects()->sync([$math->id => ['sort_order' => 1]]);
        $teacher = Staff::factory()->create();

        $this->actingAs($this->office)->put("/academics/sections/{$section->id}/assignments", ['teachers' => [$math->id => $teacher->id]])->assertRedirect();
        $this->assertSame($teacher->id, $section->assignments()->where('subject_id', $math->id)->value('staff_id'));

        $this->actingAs($this->office)->put("/academics/sections/{$section->id}/assignments", ['teachers' => [$math->id => '']])->assertRedirect();
        $this->assertSame(0, $section->assignments()->count());

        $this->actingAs($this->office)->put("/academics/sections/{$section->id}", [
            'grade_level_id' => $section->grade_level_id, 'name' => 'الف', 'shift' => 'morning', 'class_teacher_id' => $teacher->id,
        ])->assertRedirect();
        $this->assertSame($teacher->id, $section->fresh()->class_teacher_id);
    }

    public function test_section_with_students_cannot_be_deleted(): void
    {
        $year = $this->makeYear();
        $section = $this->makeSection($year);
        app(EnrollmentService::class)->enroll(Student::factory()->create(), $section);

        $this->actingAs($this->office)->delete("/academics/sections/{$section->id}")->assertRedirect();
        $this->assertModelExists($section);

        $empty = $this->makeSection($year, 8);
        $this->actingAs($this->office)->delete("/academics/sections/{$empty->id}")->assertRedirect();
        $this->assertModelMissing($empty);
    }

    public function test_only_academic_managers_change_structure(): void
    {
        $year = $this->makeYear();
        foreach ([Role::Teacher, Role::Accountant, Role::CourseManager] as $role) {
            $user = $this->userWithRole($role);
            $this->actingAs($user)->get('/academics/years')->assertForbidden();
            $this->actingAs($user)->post('/academics/sections', ['academic_year_id' => $year->id])->assertForbidden();
            $this->actingAs($user)->get('/academics/grades')->assertForbidden();
        }
        $this->actingAs($this->userWithRole(Role::Accountant))->get('/academics/sections')->assertForbidden();
    }
}
