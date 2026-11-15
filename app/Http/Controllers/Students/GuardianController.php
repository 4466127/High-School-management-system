<?php

namespace App\Http\Controllers\Students;

use App\Http\Controllers\Controller;
use App\Http\Requests\Students\GuardianRequest;
use App\Models\Guardian;
use App\Models\Student;
use App\Services\StudentService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Support\Facades\Gate;

class GuardianController extends Controller
{
    public function __construct(private readonly StudentService $students) {}

    public function store(GuardianRequest $request, Student $student): RedirectResponse
    {
        $guardian = $this->students->attachGuardian($student, $request->validated());

        return $this->backToGuardians($student, __('students.guardian_added', ['name' => $guardian->name]));
    }

    public function update(GuardianRequest $request, Student $student, Guardian $guardian): RedirectResponse
    {
        abort_unless($student->guardians()->whereKey($guardian->id)->exists(), 404);
        $this->students->updateGuardian($student, $guardian, $request->validated());

        return $this->backToGuardians($student, __('students.guardian_updated', ['name' => $guardian->name]));
    }

    public function destroy(Student $student, Guardian $guardian): RedirectResponse
    {
        Gate::authorize('update', $student);
        abort_unless($student->guardians()->whereKey($guardian->id)->exists(), 404);
        $this->students->detachGuardian($student, $guardian);

        return $this->backToGuardians($student, __('students.guardian_removed', ['name' => $guardian->name]));
    }

    private function backToGuardians(Student $student, string $message): RedirectResponse
    {
        return redirect()->to(route('students.show', $student).'#guardians')->with('success', $message);
    }
}
