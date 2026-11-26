<?php

namespace App\Http\Controllers\Students;

use App\Enums\EnrollmentStatus;
use App\Http\Controllers\Controller;
use App\Models\AcademicYear;
use App\Models\Enrollment;
use App\Models\Section;
use Illuminate\Http\Request;
use Illuminate\View\View;

/** کارت هویت دانش‌آموز: تکی (از پرونده) یا گروهی (یک بخش) — مرحله‌ی ۱۳؛ students.manage. */
class IdCardController extends Controller
{
    public function index(Request $request): View
    {
        $year = AcademicYear::current();
        $sections = $year ? Section::query()->with('gradeLevel')->withCount('activeEnrollments')->where('academic_year_id', $year->id)->ordered()->get() : collect();

        $enrollments = collect();
        if ($year && ($request->integer('section') || $request->integer('student'))) {
            $enrollments = Enrollment::query()
                ->with(['student', 'section.gradeLevel'])
                ->where('academic_year_id', $year->id)
                ->where('status', EnrollmentStatus::Active)
                ->when($request->integer('section'), fn ($q, $id) => $q->where('section_id', $id))
                ->when($request->integer('student'), fn ($q, $id) => $q->where('student_id', $id))
                ->whereHas('student')
                ->orderByRaw('roll_no IS NULL, roll_no')
                ->get();
        }

        return view($request->boolean('print') ? 'students.cards.print' : 'students.cards.index', [
            'year' => $year,
            'sections' => $sections,
            'enrollments' => $enrollments,
            'selected' => $request->integer('section') ?: null,
        ]);
    }
}
