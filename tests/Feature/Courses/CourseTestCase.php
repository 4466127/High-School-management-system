<?php

namespace Tests\Feature\Courses;

use App\Enums\CourseSeason;
use App\Enums\CourseStatus;
use App\Enums\Role;
use App\Enums\Shift;
use App\Models\Course;
use App\Models\CourseGroup;
use App\Models\Enrollment;
use App\Models\Section;
use App\Models\Staff;
use App\Models\Student;
use App\Models\User;
use App\Services\EnrollmentService;
use Carbon\Carbon;
use Carbon\CarbonImmutable;
use Tests\TestCase;

/** زمینه‌ی تست‌های کورس: کورس انگلیسی با گروه قبل از ظهر (معلم اول) و بعد از ظهر (معلم دوم)، و یک بخش با چهار دانش‌آموز. */
abstract class CourseTestCase extends TestCase
{
    protected User $manager;

    protected User $teacher;

    protected User $otherTeacher;

    protected User $accountant;

    protected Staff $teacherStaff;

    protected Staff $otherStaff;

    protected Course $course;

    protected CourseGroup $morning;

    protected CourseGroup $afternoon;

    protected Section $section;

    /** @var list<Enrollment> */
    protected array $enrollments = [];

    protected function setUp(): void
    {
        parent::setUp();
        Carbon::setTestNow('2026-11-16 09:00:00');
        CarbonImmutable::setTestNow('2026-11-16 09:00:00');

        $this->manager = $this->userWithRole(Role::CourseManager);
        $this->accountant = $this->userWithRole(Role::Accountant);
        $this->teacherStaff = Staff::factory()->create(['staff_type' => 'teacher']);
        $this->otherStaff = Staff::factory()->create(['staff_type' => 'teacher']);
        $this->teacher = $this->userWithRole(Role::Teacher, ['staff_id' => $this->teacherStaff->id]);
        $this->otherTeacher = $this->userWithRole(Role::Teacher, ['staff_id' => $this->otherStaff->id]);

        $this->course = Course::create([
            'name' => 'کورس زمستانی انگلیسی', 'field' => 'انگلیسی', 'season' => CourseSeason::Winter,
            'start_date' => '2026-11-01', 'end_date' => '2027-02-28', 'status' => CourseStatus::Active,
        ]);
        $this->morning = $this->course->groups()->create(['name' => 'سطح مبتدی', 'shift' => Shift::Morning, 'staff_id' => $this->teacherStaff->id]);
        $this->afternoon = $this->course->groups()->create(['name' => 'سطح متوسط', 'shift' => Shift::Afternoon, 'staff_id' => $this->otherStaff->id]);

        $year = $this->makeYear();
        $this->section = $this->makeSection($year, 9);
        foreach (['اول', 'دوم', 'سوم', 'چهارم'] as $name) {
            $this->enrollments[] = app(EnrollmentService::class)->enroll(Student::factory()->create(['first_name' => 'کورسی'.$name]), $this->section, '2026-03-21');
        }
    }

    /** شامل کردن دانش‌آموزان (اندیس‌های enrollments) در یک گروه */
    protected function addStudents(CourseGroup $group, array $indexes)
    {
        return $this->actingAs($this->manager)->post(route('courses.list.participants.students', $this->course), [
            'course_group_id' => $group->id,
            'student_ids' => array_map(fn ($i) => $this->enrollments[$i]->student_id, $indexes),
            'joined_on' => '۱۴۰۵/۰۸/۱۵',
        ]);
    }
}
