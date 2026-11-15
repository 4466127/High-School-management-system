<?php

namespace App\Http\Controllers\Academics;

use App\Enums\EnrollmentStatus;
use App\Enums\StaffType;
use App\Enums\StudentStatus;
use App\Http\Controllers\Controller;
use App\Http\Requests\Academics\SectionRequest;
use App\Models\AcademicYear;
use App\Models\GradeLevel;
use App\Models\Section;
use App\Models\Staff;
use App\Models\Student;
use App\Services\ActivityLogger;
use App\Services\EnrollmentService;
use App\Support\Digits;
use App\Support\ModelChanges;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;
use Illuminate\Validation\Rule;
use Illuminate\View\View;

/** بخش‌ها: فهرست سال، صفحه‌ی بخش (دانش‌آموزان، معلمان مضامین)، شامل کردن گروهی، نمبر حاضری. */
class SectionController extends Controller
{
    public function __construct(
        private readonly ActivityLogger $logger,
        private readonly EnrollmentService $enrollments,
    ) {}

    public function index(Request $request): View
    {
        Gate::authorize('viewAny', Section::class);

        $years = AcademicYear::query()->orderByDesc('start_date')->get();
        $year = $years->firstWhere('id', $request->integer('year')) ?? AcademicYear::current() ?? $years->first();
        $user = $request->user();
        $canManage = $user->can('manage', Section::class);

        $sections = $year
            ? Section::query()
                ->with(['gradeLevel', 'classTeacher'])
                ->withCount('activeEnrollments')
                ->where('academic_year_id', $year->id)
                ->when(! $canManage, fn ($q) => $q->whereIn('sections.id', $user->taughtSectionIds() ?: [0]))
                ->ordered()
                ->get()
            : collect();

        return view('academics.sections.index', [
            'years' => $years,
            'year' => $year,
            'sectionsByGrade' => $sections->groupBy(fn (Section $s) => $s->gradeLevel->name),
            'canManage' => $canManage,
            'unassignedCount' => $year && $canManage ? $this->unenrolledStudents($year)->count() : 0,
        ]);
    }

    public function create(Request $request): View
    {
        Gate::authorize('manage', Section::class);
        $year = AcademicYear::find($request->integer('year')) ?? AcademicYear::current();
        abort_unless($year, 404);

        return view('academics.sections.create', $this->formData(new Section([
            'academic_year_id' => $year->id,
            'grade_level_id' => $request->integer('grade') ?: null,
            'shift' => 'morning',
        ])));
    }

    public function store(SectionRequest $request): RedirectResponse
    {
        Gate::authorize('manage', Section::class);
        $section = Section::create($request->validated());
        $this->logger->log('section.created', $section, $section->load('gradeLevel', 'academicYear')->label.' — '.$section->academicYear->name);

        return redirect()->route('academics.sections.show', $section)->with('success', __('academics.section_created', ['name' => $section->label]));
    }

    public function show(Request $request, Section $section): View
    {
        Gate::authorize('view', $section);
        $section->load(['gradeLevel.subjects', 'academicYear', 'classTeacher', 'assignments.teacher']);

        $enrollments = $section->activeEnrollments()
            ->with('student')
            ->orderByRaw('roll_no IS NULL, roll_no')
            ->get();

        $canManage = $request->user()->can('manage', Section::class);

        return view('academics.sections.show', [
            'section' => $section,
            'enrollments' => $enrollments,
            'leftEnrollments' => $section->enrollments()->with('student')->where('status', '!=', EnrollmentStatus::Active)->get(),
            'assignments' => $section->assignments->keyBy('subject_id'),
            'teachers' => $canManage ? $this->teacherOptions() : collect(),
            'canManage' => $canManage,
            'canSeeStudents' => $request->user()->can('viewAny', Student::class),
        ]);
    }

    public function edit(Section $section): View
    {
        Gate::authorize('manage', Section::class);

        return view('academics.sections.edit', $this->formData($section));
    }

    public function update(SectionRequest $request, Section $section): RedirectResponse
    {
        Gate::authorize('manage', Section::class);
        $section->fill($request->validated());
        [$old, $new] = ModelChanges::capture($section);
        $section->save();
        if ($new !== []) {
            $this->logger->log('section.updated', $section, $section->load('gradeLevel')->label, $old, $new);
        }

        return redirect()->route('academics.sections.show', $section)->with('success', __('academics.section_updated'));
    }

    /** فقط بخشی که هیچ دانش‌آموزی (حتی سابق) ندارد حذف می‌شود */
    public function destroy(Section $section): RedirectResponse
    {
        Gate::authorize('manage', Section::class);
        if ($section->enrollments()->exists()) {
            return back()->with('error', __('academics.section_has_students'));
        }

        $label = $section->load('gradeLevel')->label;
        $yearId = $section->academic_year_id;
        DB::transaction(function () use ($section, $label) {
            $section->delete();
            $this->logger->log('section.deleted', null, $label);
        });

        return redirect()->route('academics.sections.index', ['year' => $yearId])->with('success', __('academics.section_deleted', ['name' => $label]));
    }

    /** معلم هر مضمون: teachers[subject_id] = staff_id|null */
    public function updateAssignments(Request $request, Section $section): RedirectResponse
    {
        Gate::authorize('manage', Section::class);
        $data = $request->validate([
            'teachers' => ['array'],
            'teachers.*' => ['nullable', 'integer', Rule::exists('staff', 'id')->whereNull('deleted_at')],
        ]);
        $subjectIds = $section->gradeLevel->subjects()->pluck('subjects.id')->all();

        DB::transaction(function () use ($section, $data, $subjectIds) {
            $before = $section->assignments()->pluck('staff_id', 'subject_id')->all();
            foreach ($subjectIds as $subjectId) {
                $staffId = $data['teachers'][$subjectId] ?? null;
                if ($staffId) {
                    $section->assignments()->updateOrCreate(['subject_id' => $subjectId], ['staff_id' => $staffId]);
                } else {
                    $section->assignments()->where('subject_id', $subjectId)->delete();
                }
            }
            $after = $section->assignments()->pluck('staff_id', 'subject_id')->all();
            if ($before != $after) {
                $this->logger->log('section.assignments_updated', $section, $section->label, ['teachers' => $before], ['teachers' => $after]);
            }
        });

        return redirect()->to(route('academics.sections.show', $section).'#teachers-pane')->with('success', __('academics.assignments_saved'));
    }

    public function enrollForm(Request $request, Section $section): View
    {
        Gate::authorize('manage', Section::class);
        $section->load('gradeLevel', 'academicYear');

        return view('academics.sections.enroll', [
            'section' => $section,
            'students' => $this->unenrolledStudents($section->academicYear)->orderBy('first_name')->orderBy('last_name')->get(),
        ]);
    }

    public function enroll(Request $request, Section $section): RedirectResponse
    {
        Gate::authorize('manage', Section::class);
        $data = $request->validate([
            'student_ids' => ['required', 'array', 'min:1'],
            'student_ids.*' => ['integer', Rule::exists('students', 'id')->whereNull('deleted_at')],
        ]);

        $count = DB::transaction(function () use ($data, $section) {
            $count = 0;
            foreach (Student::whereIn('id', $data['student_ids'])->orderBy('first_name')->orderBy('last_name')->get() as $student) {
                $this->enrollments->enroll($student, $section);
                $count++;
            }

            return $count;
        });

        return redirect()->route('academics.sections.show', $section)->with('success', __('academics.students_enrolled', ['count' => Digits::toPersian($count)]));
    }

    public function renumber(Section $section): RedirectResponse
    {
        Gate::authorize('manage', Section::class);
        $this->enrollments->renumber($section);

        return back()->with('success', __('academics.renumbered'));
    }

    /** دانش‌آموزان فعال (یا برگشته) که در این سال شمولیت فعال ندارند */
    private function unenrolledStudents(AcademicYear $year)
    {
        return Student::query()
            ->whereIn('status', [StudentStatus::Active, StudentStatus::Transferred, StudentStatus::Dropped])
            ->whereDoesntHave('enrollments', fn ($q) => $q->where('academic_year_id', $year->id)->where('status', EnrollmentStatus::Active))
            ->whereDoesntHave('enrollments', fn ($q) => $q->where('academic_year_id', $year->id)
                ->whereIn('status', [EnrollmentStatus::Promoted, EnrollmentStatus::Repeated, EnrollmentStatus::Graduated]));
    }

    private function formData(Section $section): array
    {
        return [
            'section' => $section,
            'year' => $section->academicYear ?? AcademicYear::find($section->academic_year_id),
            'grades' => GradeLevel::query()->where('is_active', true)->orWhere('id', $section->grade_level_id)->orderBy('sort_order')->get(),
            'teachers' => $this->teacherOptions(),
        ];
    }

    private function teacherOptions()
    {
        return Staff::query()
            ->whereIn('staff_type', [StaffType::Teacher, StaffType::Admin])
            ->where('employment_status', '!=', 'left')
            ->orderBy('first_name')
            ->get();
    }
}
