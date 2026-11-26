<?php

namespace App\Http\Controllers\Students;

use App\Http\Controllers\Controller;
use App\Http\Requests\Students\PastResultRequest;
use App\Models\GradeLevel;
use App\Models\Student;
use App\Models\StudentPastResult;
use App\Services\PastResultService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

/**
 * نتیجه‌ی سال‌هایی که در سیستم نیست (مرحله‌ی ۱۴؛ students.manage): فارغ‌های سال‌های قبل، سال‌های مکتب دیگر.
 * با انتخاب صنف، مضامین همان صنف در فرم آماده می‌شود. حذف نیست؛ باطل با دلیل.
 */
class PastResultController extends Controller
{
    /** شمار سطرهای خالی اضافه برای مضمون دیگر */
    private const EXTRA_ROWS = 3;

    public function __construct(private readonly PastResultService $service) {}

    public function create(Request $request, Student $student): View
    {
        $grade = GradeLevel::with('subjects')->find($request->integer('grade'));

        return view('students.past-results.form', [
            'student' => $student,
            'row' => null,
            'grades' => GradeLevel::query()->orderBy('sort_order')->get(),
            'grade' => $grade,
            'subjects' => $this->subjectRows($grade?->subjects->pluck('name')->all() ?? [], []),
        ]);
    }

    public function store(PastResultRequest $request, Student $student): RedirectResponse
    {
        $this->service->create($student, $request->validated(), $request->user());

        return redirect()->to(route('students.show', $student).'#history')->with('success', __('past_results.saved'));
    }

    public function edit(StudentPastResult $pastResult): View
    {
        abort_if($pastResult->isCancelled(), 404);
        $marks = collect($pastResult->subjects ?? [])->pluck('mark', 'name')->all();

        return view('students.past-results.form', [
            'student' => $pastResult->student,
            'row' => $pastResult,
            'grades' => GradeLevel::query()->orderBy('sort_order')->get(),
            'grade' => $pastResult->gradeLevel,
            'subjects' => $this->subjectRows(array_keys($marks), $marks),
        ]);
    }

    public function update(PastResultRequest $request, StudentPastResult $pastResult): RedirectResponse
    {
        $this->service->update($pastResult, $request->validated(), $request->user());

        return redirect()->to(route('students.show', $pastResult->student).'#history')->with('success', __('past_results.saved'));
    }

    public function cancel(Request $request, StudentPastResult $pastResult): RedirectResponse
    {
        $data = $request->validate(['reason' => ['required', 'string', 'max:500']], [], ['reason' => __('students.reason')]);
        $this->service->cancel($pastResult, $data['reason'], $request->user());

        return redirect()->to(route('students.show', $pastResult->student).'#history')->with('success', __('past_results.cancelled'));
    }

    /** @return list<array{name: string, mark: mixed}> */
    private function subjectRows(array $names, array $marks): array
    {
        $rows = array_map(fn ($n) => ['name' => (string) $n, 'mark' => $marks[$n] ?? null], $names);
        for ($i = 0; $i < self::EXTRA_ROWS; $i++) {
            $rows[] = ['name' => '', 'mark' => null];
        }

        return $rows;
    }
}
