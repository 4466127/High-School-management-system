<?php

namespace Tests\Feature\Exams;

use App\Enums\Role;
use App\Models\AcademicYear;
use App\Models\Enrollment;
use App\Models\Exam;
use App\Models\ExamType;
use App\Models\Section;
use App\Models\Setting;
use App\Models\Staff;
use App\Models\Student;
use App\Models\Subject;
use App\Models\User;
use App\Services\EnrollmentService;
use Carbon\Carbon;
use Carbon\CarbonImmutable;
use Tests\TestCase;

/** زمینه‌ی مشترک تست‌های امتحانات: یک بخش با دو مضمون، معلم مضمون اول، سه دانش‌آموز و دو امتحان (۴۰ + ۶۰). */
abstract class ExamTestCase extends TestCase
{
    protected AcademicYear $year;

    protected Section $section;

    protected Section $otherSection;

    protected Subject $math;

    protected Subject $dari;

    protected User $teacher;

    protected User $principal;

    protected Exam $mid;

    protected Exam $final;

    /** @var list<Enrollment> */
    protected array $students = [];

    protected function setUp(): void
    {
        parent::setUp();
        Carbon::setTestNow('2026-11-16 09:00:00');
        CarbonImmutable::setTestNow('2026-11-16 09:00:00');

        $this->year = $this->makeYear();
        Setting::put('conditional_max_failed_subjects', '1');
        $this->math = Subject::where('name', 'ریاضی')->firstOrFail();
        $this->dari = Subject::where('name', 'دری')->firstOrFail();

        $this->section = $this->makeSection($this->year, 7);
        $this->otherSection = $this->makeSection($this->year, 7, 'ب');
        $this->section->gradeLevel->subjects()->sync([$this->math->id => ['sort_order' => 1], $this->dari->id => ['sort_order' => 2]]);

        $staff = Staff::factory()->create();
        $this->teacher = $this->userWithRole(Role::Teacher, ['staff_id' => $staff->id]);
        $this->principal = $this->userWithRole(Role::Principal);
        $this->section->assignments()->create(['subject_id' => $this->math->id, 'staff_id' => $staff->id]);

        $service = app(EnrollmentService::class);
        foreach (['اول', 'دوم', 'سوم'] as $name) {
            $this->students[] = $service->enroll(Student::factory()->create(['first_name' => 'شاگرد'.$name]), $this->section, '2026-03-21');
        }
        $service->enroll(Student::factory()->create(['first_name' => 'بیگانه']), $this->otherSection, '2026-03-21');

        $this->mid = $this->makeExam('امتحان چهارونیم‌ماهه', 40, 40, 'open');
        $this->final = $this->makeExam('امتحان سالانه', 60, 60, 'open');
    }

    protected function makeExam(string $name, float $max, float $weight, string $status = 'open', bool $counts = true): Exam
    {
        return Exam::create([
            'academic_year_id' => $this->year->id,
            'exam_type_id' => ExamType::orderBy('sort_order')->value('id'),
            'name' => $name, 'max_mark' => $max, 'weight' => $weight, 'counts_in_final' => $counts, 'status' => $status,
        ]);
    }

    /** نمره‌ی یک امتحان و مضمون برای همه‌ی دانش‌آموزان بخش (از راه صفحه‌ی ثبت نمره، با کاربر مدیر) */
    protected function enterMarks(Exam $exam, Subject $subject, array $marks, ?User $user = null)
    {
        $payload = ['marks' => [], 'absent' => []];
        foreach ($this->students as $i => $enrollment) {
            $value = $marks[$i] ?? null;
            $payload['marks'][$enrollment->id] = $value === 'absent' ? '' : (string) $value;
            $payload['absent'][$enrollment->id] = $value === 'absent' ? '1' : '0';
        }

        return $this->actingAs($user ?? $this->principal)->post(route('exams.marks.store', [$exam, $this->section, $subject]), $payload);
    }

    /**
     * همه‌ی نمرات (حد مشروط = ۱ مضمون):
     * شاگرد اول: ریاضی ۹۰، دری ۷۸ ← کامیاب، اوسط ۸۴، درجه ۱
     * شاگرد دوم: ریاضی ۲۵، دری ۷۰ ← مشروط
     * شاگرد سوم: ریاضی ۱۵، دری ۲۰ ← ناکام
     */
    protected function enterAllMarks(): void
    {
        $this->enterMarks($this->mid, $this->math, [36, 10, 5]);
        $this->enterMarks($this->final, $this->math, [54, 15, 10]);
        $this->enterMarks($this->mid, $this->dari, [30, 30, 8]);
        $this->enterMarks($this->final, $this->dari, [48, 40, 12]);
    }

    protected function lockExams(): void
    {
        $this->mid->update(['status' => 'locked']);
        $this->final->update(['status' => 'locked']);
    }
}
