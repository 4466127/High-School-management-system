<?php

namespace App\Http\Controllers\Students;

use App\Enums\Gender;
use App\Enums\StudentStatus;
use App\Http\Controllers\Controller;
use App\Http\Requests\DeleteWithReasonRequest;
use App\Http\Requests\Students\StudentRequest;
use App\Http\Requests\Students\StudentStatusRequest;
use App\Models\AcademicYear;
use App\Models\Exam;
use App\Models\FeeRate;
use App\Models\FeeType;
use App\Models\Payment;
use App\Models\Section;
use App\Models\Setting;
use App\Models\Student;
use App\Services\AttendanceService;
use App\Services\FinanceService;
use App\Services\ResultService;
use App\Services\StudentService;
use App\Support\PrivateFiles;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;
use Illuminate\View\View;
use Symfony\Component\HttpFoundation\StreamedResponse;

class StudentController extends Controller
{
    public function __construct(
        private readonly StudentService $students,
        private readonly AttendanceService $attendance,
        private readonly ResultService $results,
        private readonly FinanceService $finance,
    ) {}

    /** زبانه‌ی فیس: فقط وقتی فیس روشن است و کاربر finance.view دارد */
    private function feesData(Request $request, Student $student, $yearEnrollment): ?array
    {
        if (! Setting::enabled('fees_enabled') || ! $request->user()->hasPermission('finance.view')) {
            return null;
        }

        $year = AcademicYear::current();
        $invoices = $this->finance->invoicesFor($student);
        $types = FeeType::where('is_active', true)->orderBy('sort_order')->get();
        $grade = $yearEnrollment?->section?->grade_level_id;

        return [
            'invoices' => $invoices,
            'payments' => Payment::where('student_id', $student->id)->orderByDesc('receipt_no')->get(),
            'remaining' => $invoices->sum('remaining_cents'),
            'canIssue' => $year && $yearEnrollment?->isActive(),
            'types' => $types,
            'periods' => $year ? $types->mapWithKeys(fn ($t) => [$t->id => FinanceService::periodOptions($t, $year)]) : collect(),
            'rates' => $year && $grade ? FeeRate::where('academic_year_id', $year->id)->where('grade_level_id', $grade)->pluck('amount', 'fee_type_id') : collect(),
        ];
    }

    public function index(Request $request): View
    {
        Gate::authorize('viewAny', Student::class);

        $status = (string) $request->query('status', 'active');
        $user = $request->user();
        $canManage = $user->can('viewSensitive', Student::class);
        $seesAll = $user->can('viewAll', Student::class);
        $year = AcademicYear::current();

        $query = Student::query()
            ->with('currentEnrollment.section.gradeLevel')
            ->search($request->query('q'));

        // معلم: فقط دانش‌آموزان بخش‌های خودش
        if (! $seesAll) {
            $query->inSections($user->taughtSectionIds());
        }

        $sectionId = $request->integer('section') ?: null;
        if ($sectionId) {
            $query->inSections([$sectionId]);
        }

        if ($status === 'deleted' && $canManage) {
            $query->onlyTrashed();
        } elseif (StudentStatus::tryFrom($status)) {
            $query->where('status', $status);
        }

        if ($gender = Gender::tryFrom((string) $request->query('gender'))) {
            $query->where('gender', $gender);
        }

        $sections = $year
            ? Section::query()->with('gradeLevel')->where('academic_year_id', $year->id)
                ->when(! $seesAll, fn ($q) => $q->whereIn('sections.id', $user->taughtSectionIds() ?: [0]))
                ->ordered()->get()
            : collect();

        return view('students.index', [
            'students' => $query->orderBy('first_name')->orderBy('last_name')->paginate(25)->withQueryString(),
            'status' => $status,
            'canManage' => $canManage,
            'seesAll' => $seesAll,
            'sections' => $sections,
            'sectionId' => $sectionId,
            'noTeacherLink' => ! $seesAll && ! $user->staff_id,
        ]);
    }

    public function create(): View
    {
        Gate::authorize('create', Student::class);

        return view('students.create', [
            'student' => new Student(['admission_date' => now()]),
            'nextAdmissionNo' => $this->students->nextAdmissionNo(),
        ]);
    }

    public function store(StudentRequest $request): RedirectResponse
    {
        $student = $this->students->create($request->studentData(), $request->file('photo'), $request->guardianData());

        return redirect()->route('students.show', $student)
            ->with('success', __('students.created', ['name' => $student->full_name, 'no' => $student->admission_no]));
    }

    public function show(Request $request, Student $student): View
    {
        Gate::authorize('view', $student);

        $sensitive = $request->user()->can('viewSensitive', Student::class);
        if ($sensitive) {
            $student->load(['guardians', 'documents.uploader']);
        }
        $student->load(['enrollments' => fn ($q) => $q->with(['academicYear', 'section.gradeLevel'])
            ->join('academic_years', 'academic_years.id', '=', 'enrollments.academic_year_id')
            ->orderByDesc('academic_years.start_date')
            ->select('enrollments.*')]);

        $year = AcademicYear::current();
        $current = $year ? $student->enrollments->first(fn ($e) => $e->academic_year_id === $year->id) : null;

        return view('students.show', [
            'student' => $student,
            'sensitive' => $sensitive,
            'currentYear' => $year,
            'currentEnrollment' => $current?->isActive() ? $current : null,
            'yearEnrollment' => $current,
            'attendance' => $current ? $this->attendance->enrollmentHistory($current) : null,
            'canSeeMarks' => $canSeeMarks = $current && Gate::allows('viewResults', [Exam::class, $current->section]),
            'reportCard' => $canSeeMarks ? $this->results->reportCards($current->section, $current)->first() : null,
            'fees' => $this->feesData($request, $student, $current),
            'transfers' => $sensitive ? $student->transfers()->get() : collect(),
            'pastResults' => $sensitive ? $student->pastResults()->active()->get() : collect(),
            'yearSections' => $year && $request->user()->can('update', $student)
                ? Section::query()->with('gradeLevel')->where('academic_year_id', $year->id)->ordered()->get()
                : collect(),
        ]);
    }

    public function edit(Student $student): View
    {
        Gate::authorize('update', $student);

        return view('students.edit', ['student' => $student]);
    }

    public function update(StudentRequest $request, Student $student): RedirectResponse
    {
        $this->students->update($student, $request->studentData(), $request->file('photo'), $request->boolean('remove_photo'));

        return redirect()->route('students.show', $student)->with('success', __('students.updated', ['name' => $student->full_name]));
    }

    public function changeStatus(StudentStatusRequest $request, Student $student): RedirectResponse
    {
        $data = $request->validated();
        $this->students->changeStatus($student, StudentStatus::from($data['status']), $data['reason'] ?? null, $data['date'] ?? null);

        return back()->with('success', __('students.status_changed', ['name' => $student->full_name, 'status' => $student->status->label()]));
    }

    public function destroy(DeleteWithReasonRequest $request, Student $student): RedirectResponse
    {
        Gate::authorize('delete', $student);
        $this->students->delete($student, $request->validated('reason'));

        return redirect()->route('students.index')->with('success', __('students.deleted', ['name' => $student->full_name]));
    }

    public function restore(Student $student): RedirectResponse
    {
        Gate::authorize('restore', $student);
        $this->students->restore($student);

        return redirect()->route('students.show', $student)->with('success', __('students.restored', ['name' => $student->full_name]));
    }

    public function photo(Student $student): StreamedResponse
    {
        Gate::authorize('view', $student);
        abort_unless(PrivateFiles::exists($student->photo_path), 404);

        return PrivateFiles::inline($student->photo_path);
    }
}
