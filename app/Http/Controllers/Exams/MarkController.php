<?php

namespace App\Http\Controllers\Exams;

use App\Enums\EnrollmentStatus;
use App\Enums\ExamStatus;
use App\Http\Controllers\Controller;
use App\Models\AcademicYear;
use App\Models\Enrollment;
use App\Models\Exam;
use App\Models\Mark;
use App\Models\Section;
use App\Models\Subject;
use App\Models\TeacherAssignment;
use App\Services\MarkService;
use App\Services\ResultService;
use App\Support\Digits;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;
use Illuminate\View\View;

/** ثبت نمره: انتخاب امتحان و بخش، سپس ورق نمره‌ی یک مضمون (مجوز: marks.enter؛ معلم فقط مضمون خودش). */
class MarkController extends Controller
{
    public function __construct(
        private readonly MarkService $marks,
        private readonly ResultService $results,
    ) {}

    public function index(Request $request): View
    {
        $year = AcademicYear::current();
        $user = $request->user();
        $manage = $user->hasPermission('exams.manage');

        $exams = $year ? Exam::query()->where('academic_year_id', $year->id)->ordered()->get() : collect();
        $exam = $exams->firstWhere('id', $request->integer('exam'))
            ?? $exams->firstWhere('status', ExamStatus::Open)
            ?? $exams->first();

        $rows = collect();
        $sections = collect();
        $section = null;

        if ($year && $exam) {
            if ($manage) {
                $sections = Section::query()->with('gradeLevel')->where('academic_year_id', $year->id)->ordered()->get();
                $section = $sections->firstWhere('id', $request->integer('section')) ?? $sections->first();
                if ($section) {
                    $section->load(['gradeLevel.subjects', 'assignments.teacher']);
                    $teachers = $section->assignments->keyBy('subject_id');
                    $rows = $section->gradeLevel->subjects->map(fn (Subject $s) => [
                        'section' => $section, 'subject' => $s, 'teacher' => $teachers->get($s->id)?->teacher,
                    ]);
                }
            } elseif ($user->staff_id) {
                $rows = TeacherAssignment::query()
                    ->with(['section.gradeLevel', 'subject'])
                    ->where('staff_id', $user->staff_id)
                    ->whereHas('section', fn ($q) => $q->where('academic_year_id', $year->id))
                    ->get()
                    ->sortBy(fn ($a) => [$a->section->gradeLevel->sort_order, $a->section->name, $a->subject->name])
                    ->map(fn ($a) => ['section' => $a->section, 'subject' => $a->subject, 'teacher' => null])
                    ->values();
            }

            $progress = $this->progress($exam, $rows->pluck('section.id')->unique()->all());
            $rows = $rows->map(fn ($row) => $row + [
                'entered' => $progress['marks'][$row['section']->id][$row['subject']->id] ?? 0,
                'students' => $progress['students'][$row['section']->id] ?? 0,
            ]);
        }

        return view('exams.marks.index', [
            'year' => $year,
            'exams' => $exams,
            'exam' => $exam,
            'manage' => $manage,
            'sections' => $sections,
            'section' => $section,
            'rows' => $rows,
            'noTeacherLink' => ! $manage && ! $user->staff_id,
        ]);
    }

    public function show(Request $request, Exam $exam, Section $section, Subject $subject): View
    {
        $this->authorizeSheet($exam, $section, $subject);
        $section->load(['gradeLevel', 'academicYear']);

        $enrollments = $this->marks->sheetEnrollments($exam, $section, $subject);
        $blocked = $this->blockedReason($request, $exam, $section, $subject);

        return view('exams.marks.sheet', [
            'exam' => $exam,
            'section' => $section,
            'subject' => $subject,
            'enrollments' => $enrollments,
            'marks' => $this->marks->sheetMarks($exam, $subject, $enrollments),
            'canEdit' => $blocked === null,
            'blocked' => $blocked,
        ]);
    }

    public function store(Request $request, Exam $exam, Section $section, Subject $subject): RedirectResponse
    {
        $this->authorizeSheet($exam, $section, $subject);
        $section->load(['gradeLevel', 'academicYear']);
        Gate::authorize('enterMarks', [$exam, $section, $subject]);
        if ($this->results->isFinalized($section)) {
            return back()->with('error', __('exams.blocked_finalized'));
        }

        // «۲۸٫۵» یا «28,5» ← 28.5 (ارقام فارسی را NormalizeInput پیش‌تر لاتین کرده است)
        $request->merge(['marks' => array_map(
            fn ($v) => is_string($v) ? str_replace(['٫', ','], '.', trim($v)) : $v,
            (array) $request->input('marks', []),
        )]);

        $max = (float) $exam->max_mark;
        $data = $request->validate([
            'marks' => ['array'],
            'marks.*' => ['nullable', 'numeric', 'min:0', 'max:'.$max],
            'absent' => ['array'],
            'absent.*' => ['nullable', 'in:0,1'],
            'note' => ['array'],
            'note.*' => ['nullable', 'string', 'max:255'],
        ], [
            'marks.*.max' => __('exams.mark_too_high', ['max' => Digits::toPersian(MarkService::format($max))]),
            'marks.*.numeric' => __('exams.mark_not_number'),
            'marks.*.min' => __('exams.mark_not_number'),
        ]);

        $result = $this->marks->save($exam, $section, $subject, $data['marks'] ?? [], $data['absent'] ?? [], $data['note'] ?? [], $request->user());

        return redirect()->route('exams.marks.show', [$exam, $section, $subject])->with('success', __('exams.marks_saved', [
            'entered' => Digits::toPersian((string) $result['entered']),
            'changed' => Digits::toPersian((string) $result['changed']),
        ]));
    }

    /** مضمون باید از مضامین صنف و امتحان از همان سال بخش باشد؛ کاربر باید معلم مضمون یا مدیر باشد */
    private function authorizeSheet(Exam $exam, Section $section, Subject $subject): void
    {
        abort_unless($exam->academic_year_id === $section->academic_year_id, 404);
        abort_unless($section->gradeLevel()->first()?->subjects()->where('subjects.id', $subject->id)->exists(), 404);
        Gate::authorize('viewMarks', [Exam::class, $section, $subject]);
    }

    private function blockedReason(Request $request, Exam $exam, Section $section, Subject $subject): ?string
    {
        return match (true) {
            $section->academicYear->isClosed() => __('exams.blocked_closed'),
            $exam->status === ExamStatus::Draft => __('exams.blocked_draft'),
            $this->results->isFinalized($section) => __('exams.blocked_finalized'),
            Gate::allows('enterMarks', [$exam, $section, $subject]) => null,
            $exam->status === ExamStatus::Locked => __('exams.blocked_locked'),
            default => __('exams.blocked_permission'),
        };
    }

    /** شمار نمره‌های ثبت‌شده برای هر بخش و مضمون، و شمار شاملان هر بخش */
    private function progress(Exam $exam, array $sectionIds): array
    {
        $marks = [];
        Mark::query()
            ->join('enrollments', 'enrollments.id', '=', 'marks.enrollment_id')
            ->where('marks.exam_id', $exam->id)
            ->whereIn('enrollments.section_id', $sectionIds ?: [0])
            ->where(fn ($q) => $q->whereNotNull('marks.mark')->orWhere('marks.is_absent', true))
            ->groupBy('enrollments.section_id', 'marks.subject_id')
            ->selectRaw('enrollments.section_id as section_id, marks.subject_id as subject_id, COUNT(*) as total')
            ->get()
            ->each(function ($row) use (&$marks) {
                $marks[$row->section_id][$row->subject_id] = (int) $row->total;
            });

        $students = Enrollment::query()
            ->whereIn('section_id', $sectionIds ?: [0])
            ->whereIn('status', [EnrollmentStatus::Active, EnrollmentStatus::Promoted, EnrollmentStatus::Repeated, EnrollmentStatus::Graduated])
            ->groupBy('section_id')
            ->selectRaw('section_id, COUNT(*) as total')
            ->pluck('total', 'section_id')
            ->map(fn ($n) => (int) $n)
            ->all();

        return ['marks' => $marks, 'students' => $students];
    }
}
