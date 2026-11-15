<?php

namespace Tests\Feature\Academics;

use App\Enums\EnrollmentStatus;
use App\Enums\Role;
use App\Enums\StudentStatus;
use App\Models\Enrollment;
use App\Models\Student;
use App\Services\EnrollmentService;
use Tests\TestCase;

/** معیار پذیرش مرحله‌ی ۴: بعد از ارتقا، سوابق سال قبل دست‌نخورده دیده می‌شود. */
class PromotionTest extends TestCase
{
    public function test_promotion_keeps_previous_year_and_creates_new_enrollments(): void
    {
        $office = $this->userWithRole(Role::Office);
        $y1405 = $this->makeYear('1405');
        $y1406 = $this->makeYear('1406', '2027-03-21', '2027-12-21', false);
        $grade7 = $this->makeSection($y1405, 7);
        $grade8 = $this->makeSection($y1406, 8);
        $grade7next = $this->makeSection($y1406, 7);

        $service = app(EnrollmentService::class);
        [$pass, $repeat, $later] = Student::factory()->count(3)->create();
        $e1 = $service->enroll($pass, $grade7, '2026-03-21');
        $e2 = $service->enroll($repeat, $grade7, '2026-03-21');
        $e3 = $service->enroll($later, $grade7, '2026-03-21');
        $snapshot = fn ($e) => [$e->academic_year_id, $e->section_id, $e->roll_no, $e->enrolled_on->toDateString()];
        $before = $snapshot($e1->fresh());

        $this->actingAs($office)->post('/academics/promotion', [
            'section_id' => $grade7->id,
            'target_year_id' => $y1406->id,
            'promote_section_id' => $grade8->id,
            'repeat_section_id' => $grade7next->id,
            'date' => '1406/01/01',
            'decisions' => [$e1->id => 'promote', $e2->id => 'repeat', $e3->id => 'skip'],
        ])->assertRedirect(route('academics.sections.show', $grade7));

        // سال ۱۴۰۵ دست‌نخورده (فقط وضعیت پایان سال)
        $this->assertSame($before, $snapshot($e1->fresh()));
        $this->assertSame(EnrollmentStatus::Promoted, $e1->fresh()->status);
        $this->assertSame(EnrollmentStatus::Repeated, $e2->fresh()->status);
        $this->assertSame(EnrollmentStatus::Active, $e3->fresh()->status);

        // سال ۱۴۰۶
        $this->assertSame($grade8->id, Enrollment::where('student_id', $pass->id)->where('academic_year_id', $y1406->id)->value('section_id'));
        $this->assertSame($grade7next->id, Enrollment::where('student_id', $repeat->id)->where('academic_year_id', $y1406->id)->value('section_id'));
        $this->assertFalse(Enrollment::where('student_id', $later->id)->where('academic_year_id', $y1406->id)->exists());
        $this->assertSame('2027-03-21', Enrollment::where('student_id', $pass->id)->where('academic_year_id', $y1406->id)->first()->enrolled_on->toDateString());

        // پرونده هر دو سال را نشان می‌دهد
        $this->actingAs($office)->get("/students/{$pass->id}")
            ->assertSee('صنف هفتم')->assertSee('صنف هشتم')->assertSee('ارتقا');

        $this->assertDatabaseHas('activity_logs', ['action' => 'student.promoted', 'subject_id' => $pass->id]);
        $this->assertDatabaseHas('activity_logs', ['action' => 'section.promoted', 'subject_id' => $grade7->id]);
    }

    public function test_promotion_validates_target_sections(): void
    {
        $office = $this->userWithRole(Role::Office);
        $y1405 = $this->makeYear('1405');
        $y1406 = $this->makeYear('1406', '2027-03-21', '2027-12-21', false);
        $grade7 = $this->makeSection($y1405, 7);
        $wrongGrade = $this->makeSection($y1406, 9);
        $e = app(EnrollmentService::class)->enroll(Student::factory()->create(), $grade7);

        $this->actingAs($office)->post('/academics/promotion', [
            'section_id' => $grade7->id, 'target_year_id' => $y1406->id, 'promote_section_id' => $wrongGrade->id,
            'decisions' => [$e->id => 'promote'],
        ])->assertSessionHasErrors('promote_section_id');

        $this->actingAs($office)->post('/academics/promotion', [
            'section_id' => $grade7->id, 'target_year_id' => $y1405->id, 'decisions' => [$e->id => 'skip'],
        ])->assertSessionHasErrors('target_year_id');

        $this->actingAs($office)->post('/academics/promotion', [
            'section_id' => $grade7->id, 'target_year_id' => $y1406->id, 'decisions' => [$e->id => 'graduate'],
        ])->assertSessionHasErrors('decisions');

        $this->assertSame(EnrollmentStatus::Active, $e->fresh()->status);
    }

    public function test_last_grade_students_graduate(): void
    {
        $office = $this->userWithRole(Role::Office);
        $y1405 = $this->makeYear('1405');
        $y1406 = $this->makeYear('1406', '2027-03-21', '2027-12-21', false);
        $grade12 = $this->makeSection($y1405, 12);
        $student = Student::factory()->create();
        $e = app(EnrollmentService::class)->enroll($student, $grade12);

        $this->actingAs($office)->post('/academics/promotion', [
            'section_id' => $grade12->id, 'target_year_id' => $y1406->id, 'decisions' => [$e->id => 'graduate'],
        ])->assertRedirect();

        $this->assertSame(EnrollmentStatus::Graduated, $e->fresh()->status);
        $this->assertSame(StudentStatus::Graduated, $student->fresh()->status);
        $this->assertFalse(Enrollment::where('academic_year_id', $y1406->id)->exists());

        // فارغ دوباره شامل نمی‌شود
        $this->actingAs($office)->post("/students/{$student->id}/enrollment", ['section_id' => $grade12->id])->assertSessionHasErrors('section_id');
    }

    public function test_only_student_managers_can_promote(): void
    {
        $this->makeYear();
        $this->actingAs($this->userWithRole(Role::Teacher))->get('/academics/promotion')->assertForbidden();
        $this->actingAs($this->userWithRole(Role::Office))->get('/academics/promotion')->assertOk();
    }
}
