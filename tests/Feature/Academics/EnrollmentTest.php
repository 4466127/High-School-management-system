<?php

namespace Tests\Feature\Academics;

use App\Enums\EnrollmentStatus;
use App\Enums\Role;
use App\Enums\StudentStatus;
use App\Models\Enrollment;
use App\Models\Student;
use App\Models\User;
use App\Services\EnrollmentService;
use Tests\TestCase;

class EnrollmentTest extends TestCase
{
    private User $office;

    protected function setUp(): void
    {
        parent::setUp();
        $this->office = $this->userWithRole(Role::Office);
    }

    public function test_student_is_enrolled_from_profile_with_roll_number(): void
    {
        $year = $this->makeYear();
        $section = $this->makeSection($year);
        [$a, $b] = Student::factory()->count(2)->create();

        $this->actingAs($this->office)->post("/students/{$a->id}/enrollment", ['section_id' => $section->id, 'date' => '1405/01/10'])->assertRedirect();
        $this->actingAs($this->office)->post("/students/{$b->id}/enrollment", ['section_id' => $section->id])->assertRedirect();

        $this->assertSame([1, 2], Enrollment::where('section_id', $section->id)->orderBy('id')->pluck('roll_no')->all());
        $this->assertSame('2026-03-30', $a->enrollments()->first()->enrolled_on->toDateString());
        $this->assertDatabaseHas('activity_logs', ['action' => 'student.enrolled', 'subject_id' => $a->id]);
        $this->actingAs($this->office)->get("/students/{$a->id}")->assertSee('صنف هفتم — الف');
    }

    public function test_student_cannot_be_enrolled_twice_in_one_year(): void
    {
        $year = $this->makeYear();
        [$s1, $s2] = [$this->makeSection($year), $this->makeSection($year, 7, 'ب')];
        $student = Student::factory()->create();

        $this->actingAs($this->office)->post("/students/{$student->id}/enrollment", ['section_id' => $s1->id]);
        $this->actingAs($this->office)->post("/students/{$student->id}/enrollment", ['section_id' => $s2->id])->assertSessionHasErrors('section_id');

        $this->assertSame(1, $student->enrollments()->count());
    }

    public function test_inactive_student_cannot_be_enrolled(): void
    {
        $section = $this->makeSection($this->makeYear());
        $student = Student::factory()->inactive()->create();

        $this->actingAs($this->office)->post("/students/{$student->id}/enrollment", ['section_id' => $section->id])->assertSessionHasErrors('section_id');
    }

    public function test_change_section_within_same_year(): void
    {
        $year = $this->makeYear();
        [$s1, $s2] = [$this->makeSection($year), $this->makeSection($year, 7, 'ب')];
        $student = Student::factory()->create();
        $this->actingAs($this->office)->post("/students/{$student->id}/enrollment", ['section_id' => $s1->id]);

        $this->actingAs($this->office)->patch("/students/{$student->id}/enrollment/move", ['section_id' => $s2->id, 'reason' => 'درخواست خانواده'])->assertRedirect();

        $this->assertSame($s2->id, $student->enrollments()->first()->section_id);
        $this->assertSame(1, $student->enrollments()->count());
        $this->assertDatabaseHas('activity_logs', ['action' => 'student.section_changed', 'subject_id' => $student->id]);

        $otherYearSection = $this->makeSection($this->makeYear('1406', '2027-03-21', '2027-12-21', false));
        $this->actingAs($this->office)->patch("/students/{$student->id}/enrollment/move", ['section_id' => $otherYearSection->id])->assertSessionHasErrors('section_id');
    }

    public function test_transfer_and_drop_out_then_return(): void
    {
        $section = $this->makeSection($this->makeYear());
        $student = Student::factory()->create();
        $this->actingAs($this->office)->post("/students/{$student->id}/enrollment", ['section_id' => $section->id]);

        $this->actingAs($this->office)->patch("/students/{$student->id}/enrollment/leave", ['status' => 'transferred'])->assertSessionHasErrors('reason');
        $this->actingAs($this->office)->patch("/students/{$student->id}/enrollment/leave", ['status' => 'transferred', 'reason' => 'انتقال به لیسه‌ی دیگر', 'date' => '1405/06/01'])
            ->assertRedirect();

        $enrollment = $student->enrollments()->first();
        $this->assertSame(EnrollmentStatus::Transferred, $enrollment->status);
        $this->assertSame('2026-08-23', $enrollment->left_on->toDateString());
        $this->assertSame(StudentStatus::Transferred, $student->fresh()->status);
        $this->assertDatabaseHas('activity_logs', ['action' => 'student.transferred', 'subject_id' => $student->id]);

        // برگشت در همان سال: همان سطر دوباره فعال می‌شود
        $this->actingAs($this->office)->post("/students/{$student->id}/enrollment", ['section_id' => $section->id])->assertRedirect();
        $this->assertSame(1, $student->enrollments()->count());
        $this->assertSame(EnrollmentStatus::Active, $student->enrollments()->first()->status);
        $this->assertSame(StudentStatus::Active, $student->fresh()->status);
    }

    public function test_bulk_enroll_from_section_page(): void
    {
        $year = $this->makeYear();
        $section = $this->makeSection($year);
        $students = Student::factory()->count(3)->create();
        $already = Student::factory()->create();
        app(EnrollmentService::class)->enroll($already, $this->makeSection($year, 8));

        $this->actingAs($this->office)->get("/academics/sections/{$section->id}/enroll")
            ->assertOk()
            ->assertSee($students[0]->full_name)
            ->assertDontSee('value="'.$already->id.'"', false);

        $this->actingAs($this->office)->post("/academics/sections/{$section->id}/enroll", ['student_ids' => $students->pluck('id')->all()])->assertRedirect();
        $this->assertSame(3, $section->activeEnrollments()->count());

        // تعداد دانش‌آموزان در کارت بخش‌ها و فهرست ارتقا (withCount همراه ordered)
        $this->actingAs($this->office)->get('/academics/sections')
            ->assertViewHas('sectionsByGrade', fn ($groups) => $groups->flatten()->firstWhere('id', $section->id)->active_enrollments_count === 3);
        $this->actingAs($this->office)->get('/academics/promotion')
            ->assertViewHas('sourceSections', fn ($sections) => $sections->firstWhere('id', $section->id)->active_enrollments_count === 3);
    }

    public function test_renumber_by_name(): void
    {
        $section = $this->makeSection($this->makeYear());
        $service = app(EnrollmentService::class);
        $service->enroll(Student::factory()->create(['first_name' => 'یما']), $section);
        $service->enroll(Student::factory()->create(['first_name' => 'احمد']), $section);

        $this->actingAs($this->office)->patch("/academics/sections/{$section->id}/renumber")->assertRedirect();

        $rolls = $section->activeEnrollments()->with('student')->orderBy('roll_no')->get()->map(fn ($e) => [$e->roll_no, $e->student->first_name])->all();
        $this->assertSame([[1, 'احمد'], [2, 'یما']], $rolls);
    }

    public function test_view_only_roles_cannot_enroll(): void
    {
        $section = $this->makeSection($this->makeYear());
        $student = Student::factory()->create();

        $this->actingAs($this->userWithRole(Role::Accountant))->post("/students/{$student->id}/enrollment", ['section_id' => $section->id])->assertForbidden();
    }
}
