<?php

namespace App\Http\Controllers\Students;

use App\Enums\Gender;
use App\Enums\StudentStatus;
use App\Http\Controllers\Controller;
use App\Http\Requests\DeleteWithReasonRequest;
use App\Http\Requests\Students\StudentRequest;
use App\Http\Requests\Students\StudentStatusRequest;
use App\Models\Student;
use App\Services\StudentService;
use App\Support\PrivateFiles;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;
use Illuminate\View\View;
use Symfony\Component\HttpFoundation\StreamedResponse;

class StudentController extends Controller
{
    public function __construct(private readonly StudentService $students) {}

    public function index(Request $request): View
    {
        Gate::authorize('viewAny', Student::class);

        $status = (string) $request->query('status', 'active');
        $canManage = $request->user()->can('viewSensitive', Student::class);

        $query = Student::query()->search($request->query('q'));

        if ($status === 'deleted' && $canManage) {
            $query->onlyTrashed();
        } elseif (StudentStatus::tryFrom($status)) {
            $query->where('status', $status);
        }

        if ($gender = Gender::tryFrom((string) $request->query('gender'))) {
            $query->where('gender', $gender);
        }

        return view('students.index', [
            'students' => $query->orderBy('first_name')->orderBy('last_name')->paginate(25)->withQueryString(),
            'status' => $status,
            'canManage' => $canManage,
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

        return view('students.show', ['student' => $student, 'sensitive' => $sensitive]);
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
