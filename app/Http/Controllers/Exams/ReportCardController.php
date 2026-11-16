<?php

namespace App\Http\Controllers\Exams;

use App\Http\Controllers\Controller;
use App\Models\AcademicYear;
use App\Models\Enrollment;
use App\Models\Exam;
use App\Models\Section;
use App\Services\ResultService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;
use Illuminate\View\View;

/** کارنامه: فهرست دانش‌آموزان بخش، چاپ تکی و چاپ گروهی (A4). */
class ReportCardController extends Controller
{
    public function __construct(private readonly ResultService $results) {}

    public function index(Request $request): View
    {
        abort_unless($request->user()->hasPermission('reportcards.print') || $request->user()->hasPermission('exams.manage'), 403);

        $year = AcademicYear::current();
        $sections = $year ? $this->results->visibleSections($request->user(), $year) : collect();
        $section = null;
        if ($request->integer('section')) {
            $section = $sections->firstWhere('id', $request->integer('section'));
            abort_unless($section, 403);
        }
        $section ??= $sections->first();

        return view('exams.reportcards.index', [
            'year' => $year,
            'sections' => $sections,
            'section' => $section,
            'enrollments' => $section ? $this->results->sectionEnrollments($section) : collect(),
            'finalized' => $section && $this->results->isFinalized($section),
        ]);
    }

    public function show(Enrollment $enrollment): View
    {
        $section = $enrollment->section()->with(['gradeLevel', 'academicYear', 'classTeacher'])->firstOrFail();
        Gate::authorize('viewResults', [Exam::class, $section]);

        return view('exams.reportcards.print', [
            'section' => $section,
            'cards' => $this->results->reportCards($section, $enrollment->load('student')),
        ]);
    }

    public function section(Section $section): View
    {
        Gate::authorize('viewResults', [Exam::class, $section]);
        $section->load(['gradeLevel', 'academicYear', 'classTeacher']);

        return view('exams.reportcards.print', [
            'section' => $section,
            'cards' => $this->results->reportCards($section),
        ]);
    }
}
