<?php

namespace App\Http\Controllers\Exams;

use App\Http\Controllers\Controller;
use App\Models\AcademicYear;
use App\Models\Section;
use App\Services\ReexamService;
use App\Services\ResultService;
use App\Support\Digits;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\ValidationException;
use Illuminate\View\View;

/** امتحان مشروطی: ورق نمره‌ی دانش‌آموزان مشروط هر بخش، نهایی‌کردن و لغو آن (مجوز: exams.manage). */
class ReexamController extends Controller
{
    public function __construct(
        private readonly ReexamService $reexam,
        private readonly ResultService $results,
    ) {}

    public function index(Request $request): View
    {
        $year = AcademicYear::current();
        $sections = $year ? $this->results->visibleSections($request->user(), $year) : collect();
        $section = $request->integer('section') ? $sections->firstWhere('id', $request->integer('section')) : null;
        abort_if($request->integer('section') && ! $section, 404);
        $section ??= $sections->first();

        $data = ['year' => $year, 'sections' => $sections, 'section' => $section, 'rows' => collect(), 'resultsFinalized' => false, 'reexamFinalized' => false];
        if ($section) {
            $section->load(['gradeLevel', 'academicYear']);
            $data['resultsFinalized'] = $this->results->isFinalized($section);
            $data['rows'] = $this->reexam->sheet($section);
            $data['reexamFinalized'] = $data['rows']->contains(fn ($row) => $row['finalized']);
        }

        return view('exams.reexam.index', $data + ['print' => $request->boolean('print')]);
    }

    public function store(Request $request, Section $section): RedirectResponse
    {
        try {
            $done = $this->reexam->save($section, (array) $request->input('marks', []), (array) $request->input('absent', []), $request->user());
        } catch (ValidationException $e) {
            return back()->withErrors($e->errors())->withInput();
        }

        return redirect()->route('exams.reexam.index', ['section' => $section->id])->with('success', __('exams.marks_saved', [
            'entered' => Digits::toPersian((string) $done['entered']), 'changed' => Digits::toPersian((string) $done['changed']),
        ]));
    }

    public function finalize(Request $request, Section $section): RedirectResponse
    {
        try {
            $counts = $this->reexam->finalize($section, $request->user());
        } catch (ValidationException $e) {
            return back()->with('error', collect($e->errors())->flatten()->first());
        }

        $summary = collect($counts)->map(fn ($n, $label) => $label.': '.Digits::toPersian((string) $n))->join('، ');

        return redirect()->route('exams.reexam.index', ['section' => $section->id])->with('success', __('exams.reexam_finalized', ['summary' => $summary]));
    }

    public function unfinalize(Request $request, Section $section): RedirectResponse
    {
        $data = $request->validate(['reason' => ['required', 'string', 'max:500']], [], ['reason' => __('students.reason')]);

        try {
            $this->reexam->unfinalize($section, $request->user(), $data['reason']);
        } catch (ValidationException $e) {
            return back()->with('error', collect($e->errors())->flatten()->first());
        }

        return redirect()->route('exams.reexam.index', ['section' => $section->id])->with('success', __('exams.reexam_unfinalized'));
    }
}
