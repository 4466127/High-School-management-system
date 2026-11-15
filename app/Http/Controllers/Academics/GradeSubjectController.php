<?php

namespace App\Http\Controllers\Academics;

use App\Http\Controllers\Controller;
use App\Http\Requests\Academics\GradeLevelRequest;
use App\Http\Requests\Academics\SubjectRequest;
use App\Models\GradeLevel;
use App\Models\Subject;
use App\Services\ActivityLogger;
use App\Support\ModelChanges;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;
use Illuminate\View\View;

/** صنف‌ها، مضامین و مضامین هر صنف (مجوز: academics.manage). */
class GradeSubjectController extends Controller
{
    public function __construct(private readonly ActivityLogger $logger) {}

    public function index(): View
    {
        return view('academics.grades.index', [
            'grades' => GradeLevel::query()->withCount('subjects')->orderBy('sort_order')->get(),
            'subjects' => Subject::query()->withCount('gradeLevels')->orderBy('name')->get(),
            'nextSortOrder' => ((int) GradeLevel::max('sort_order')) + 1,
        ]);
    }

    public function storeGrade(GradeLevelRequest $request): RedirectResponse
    {
        $grade = GradeLevel::create($request->validated());
        $this->logger->log('grade.created', $grade, $grade->name);

        return redirect()->route('academics.grades.index')->with('success', __('academics.grade_saved', ['name' => $grade->name]));
    }

    public function updateGrade(GradeLevelRequest $request, GradeLevel $grade): RedirectResponse
    {
        $grade->fill($request->validated());
        [$old, $new] = ModelChanges::capture($grade);
        $grade->save();
        if ($new !== []) {
            $this->logger->log('grade.updated', $grade, $grade->name, $old, $new);
        }

        return redirect()->route('academics.grades.index')->with('success', __('academics.grade_saved', ['name' => $grade->name]));
    }

    public function editSubjects(GradeLevel $grade): View
    {
        return view('academics.grades.subjects', [
            'grade' => $grade->load('subjects'),
            'subjects' => Subject::query()->where('is_active', true)->orWhereIn('id', $grade->subjects->pluck('id'))->orderBy('name')->get(),
        ]);
    }

    /** مضامین یک صنف و ترتیب آن‌ها در کارنامه: selected[] و order[subject_id] */
    public function updateSubjects(Request $request, GradeLevel $grade): RedirectResponse
    {
        $data = $request->validate([
            'selected' => ['array'],
            'selected.*' => ['integer', Rule::exists('subjects', 'id')],
            'order' => ['array'],
            'order.*' => ['nullable', 'integer', 'min:1', 'max:99'],
        ]);

        $selected = array_map('intval', $data['selected'] ?? []);
        $sync = [];
        foreach ($selected as $i => $subjectId) {
            $sync[$subjectId] = ['sort_order' => (int) ($data['order'][$subjectId] ?? $i + 1)];
        }

        DB::transaction(function () use ($grade, $sync) {
            $old = $grade->subjects()->pluck('name')->all();
            $grade->subjects()->sync($sync);
            $new = $grade->subjects()->pluck('name')->all();
            $this->logger->log('grade.subjects_updated', $grade, $grade->name, ['subjects' => $old], ['subjects' => $new]);
        });

        return redirect()->route('academics.grades.index')->with('success', __('academics.grade_subjects_saved', ['name' => $grade->name]));
    }

    public function storeSubject(SubjectRequest $request): RedirectResponse
    {
        $subject = Subject::create($request->validated());
        $this->logger->log('subject.created', $subject, $subject->name);

        return redirect()->to(route('academics.grades.index').'#subjects')->with('success', __('academics.subject_saved', ['name' => $subject->name]));
    }

    public function updateSubject(SubjectRequest $request, Subject $subject): RedirectResponse
    {
        $subject->fill($request->validated());
        [$old, $new] = ModelChanges::capture($subject);
        $subject->save();
        if ($new !== []) {
            $this->logger->log('subject.updated', $subject, $subject->name, $old, $new);
        }

        return redirect()->to(route('academics.grades.index').'#subjects')->with('success', __('academics.subject_saved', ['name' => $subject->name]));
    }
}
