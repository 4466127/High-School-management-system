<?php

namespace App\Http\Controllers\Exams;

use App\Http\Controllers\Controller;
use App\Models\AcademicYear;
use App\Models\Exam;
use App\Models\Section;
use App\Models\Student;
use App\Services\ResultService;
use App\Services\TranscriptService;
use App\Support\ExamTemplate;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;
use Illuminate\View\View;

/**
 * سوانح تحصیلی چندساله / ترانسکریپت (مرحله‌ی ۱۴). چاپ تکی (جست‌وجوی دانش‌آموز، شامل فارغ‌ها و سابق‌ها) یا گروهی برای یک بخش.
 * چاپ تکی: exams.manage یا students.manage؛ چاپ بخش: همان مجوز کارنامه (ExamPolicy::viewResults).
 */
class TranscriptController extends Controller
{
    public function __construct(
        private readonly TranscriptService $transcripts,
        private readonly ResultService $results,
    ) {}

    public function index(Request $request): View
    {
        $user = $request->user();
        abort_unless($user->hasPermission('reportcards.print') || $user->hasPermission('exams.manage'), 403);

        $year = AcademicYear::current();
        $levels = $this->transcripts->gradeLevels();
        $canSearch = $this->canPrintAny($request);
        $q = trim((string) $request->query('q', ''));

        return view('exams.transcripts.index', [
            'levels' => $levels,
            'sections' => $year ? $this->results->visibleSections($user, $year)->whereIn('grade_level_id', $levels->pluck('id'))->values() : collect(),
            'canSearch' => $canSearch,
            'q' => $q,
            'students' => $canSearch && $q !== ''
                ? Student::query()->withTrashed()->search($q)->orderBy('first_name')->limit(30)->get()
                : collect(),
        ]);
    }

    public function student(Request $request, Student $student): View
    {
        abort_unless($this->canPrintAny($request), 403);

        return view('exams.transcripts.print', [
            'items' => [['student' => $student, 'data' => $this->transcripts->build($student)]],
            'tpl' => ExamTemplate::transcript(),
        ]);
    }

    public function section(Section $section): View
    {
        Gate::authorize('viewResults', [Exam::class, $section]);
        $levels = $this->transcripts->gradeLevels();

        return view('exams.transcripts.print', [
            'items' => $this->results->sectionEnrollments($section)
                ->map(fn ($e) => ['student' => $e->student, 'data' => $this->transcripts->build($e->student, $levels)])->all(),
            'tpl' => ExamTemplate::transcript(),
        ]);
    }

    private function canPrintAny(Request $request): bool
    {
        return $request->user()->hasPermission('exams.manage') || $request->user()->hasPermission('students.manage');
    }
}
