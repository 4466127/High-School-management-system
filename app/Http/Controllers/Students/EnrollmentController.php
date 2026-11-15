<?php

namespace App\Http\Controllers\Students;

use App\Enums\EnrollmentStatus;
use App\Http\Controllers\Controller;
use App\Http\Requests\Students\EnrollmentRequest;
use App\Models\AcademicYear;
use App\Models\Enrollment;
use App\Models\Section;
use App\Models\Student;
use App\Services\EnrollmentService;
use Illuminate\Http\RedirectResponse;

/** شمولیت از پرونده‌ی دانش‌آموز: شامل کردن، تغییر بخش، انتقال/ترک تحصیل (سال جاری). */
class EnrollmentController extends Controller
{
    public function __construct(private readonly EnrollmentService $enrollments) {}

    public function store(EnrollmentRequest $request, Student $student): RedirectResponse
    {
        $section = Section::findOrFail($request->validated('section_id'));
        abort_unless($section->academic_year_id === AcademicYear::current()?->id, 422, __('academics.only_current_year'));

        $this->enrollments->enroll($student, $section, $request->validated('date'));

        return $this->back($student, __('academics.enrolled', ['section' => $section->load('gradeLevel')->label]));
    }

    public function move(EnrollmentRequest $request, Student $student): RedirectResponse
    {
        $this->enrollments->changeSection($this->current($student), Section::findOrFail($request->validated('section_id')), $request->validated('reason'));

        return $this->back($student, __('academics.section_changed'));
    }

    public function leave(EnrollmentRequest $request, Student $student): RedirectResponse
    {
        $data = $request->validated();
        $status = EnrollmentStatus::from($data['status']);
        $this->enrollments->leave($this->current($student), $status, $data['date'] ?? null, $data['reason']);

        return $this->back($student, __($status === EnrollmentStatus::Transferred ? 'academics.transferred' : 'academics.dropped'));
    }

    private function current(Student $student): Enrollment
    {
        return Enrollment::query()
            ->where('student_id', $student->id)
            ->where('academic_year_id', AcademicYear::current()?->id ?? 0)
            ->where('status', EnrollmentStatus::Active)
            ->firstOr(fn () => abort(404));
    }

    private function back(Student $student, string $message): RedirectResponse
    {
        return redirect()->to(route('students.show', $student).'#history')->with('success', $message);
    }
}
