<?php

namespace App\Http\Controllers\Exams;

use App\Enums\FinalResult;
use App\Http\Controllers\Controller;
use App\Models\AcademicYear;
use App\Models\Exam;
use App\Models\Section;
use App\Services\MarkService;
use App\Services\ResultService;
use App\Support\Csv;
use App\Support\Digits;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;
use Illuminate\Validation\ValidationException;
use Illuminate\View\View;
use Symfony\Component\HttpFoundation\StreamedResponse;

/** جدول نتایج بخش (یک امتحان یا نتیجه‌ی نهایی)، نهایی‌کردن و لغو آن؛ چاپ و CSV. */
class ResultController extends Controller
{
    public function __construct(private readonly ResultService $results) {}

    public function index(Request $request): View
    {
        $data = $this->load($request);

        return view('exams.results.index', $data + ['print' => $request->boolean('print')]);
    }

    public function export(Request $request): StreamedResponse
    {
        $data = $this->load($request);
        abort_unless($data['section'], 404);
        $subjects = $data['subjects'];

        if ($data['exam']) {
            $header = array_merge([__('academics.roll_no'), __('students.full_name'), __('students.father_name')], $subjects->pluck('name')->all(), [__('exams.total'), __('exams.percent')]);
            $rows = $data['examRows']->map(fn ($row) => array_merge(
                [$row['enrollment']->roll_no, $row['enrollment']->student->full_name, $row['enrollment']->student->father_name],
                $subjects->map(fn ($s) => $this->cell($row['marks'][$s->id] ?? null))->all(),
                [MarkService::format($row['total']), MarkService::format($row['percent'])],
            ));

            return Csv::download("results-exam-{$data['exam']->id}-section-{$data['section']->id}.csv", $header, $rows);
        }

        $header = array_merge(
            [__('academics.roll_no'), __('students.full_name'), __('students.father_name')],
            $subjects->pluck('name')->all(),
            [__('exams.total'), __('exams.average'), __('exams.result'), __('exams.grade')],
        );
        $rows = $data['rows']->map(function ($card) use ($subjects) {
            $finals = collect($card['subjects'])->keyBy('id');

            return array_merge(
                [$card['enrollment']->roll_no, $card['enrollment']->student->full_name, $card['enrollment']->student->father_name],
                $subjects->map(fn ($s) => MarkService::format($finals[$s->id]['final'] ?? null))->all(),
                [
                    MarkService::format($card['total']), MarkService::format($card['average']),
                    $card['result'] ? FinalResult::from($card['result'])->label() : __('exams.incomplete'),
                    $card['grade'] ? __('exams.grades.'.$card['grade']) : '',
                ],
            );
        });

        return Csv::download("results-final-section-{$data['section']->id}.csv", $header, $rows);
    }

    public function finalize(Section $section): RedirectResponse
    {
        Gate::authorize('manage', Exam::class);

        try {
            $done = $this->results->finalize($section, request()->user());
        } catch (ValidationException $e) {
            return back()->with('error', collect($e->errors())->flatten()->first());
        }

        return redirect()->route('exams.results.index', ['section' => $section->id])
            ->with('success', __('exams.finalized', ['count' => Digits::toPersian((string) $done['finalized'])]));
    }

    public function unfinalize(Request $request, Section $section): RedirectResponse
    {
        Gate::authorize('manage', Exam::class);
        $data = $request->validate(['reason' => ['required', 'string', 'max:500']], [], ['reason' => __('students.reason')]);

        try {
            $this->results->unfinalize($section, $request->user(), $data['reason']);
        } catch (ValidationException $e) {
            return back()->with('error', collect($e->errors())->flatten()->first());
        }

        return redirect()->route('exams.results.index', ['section' => $section->id])->with('success', __('exams.unfinalized'));
    }

    private function load(Request $request): array
    {
        $user = $request->user();
        abort_unless($user->hasPermission('exams.manage') || $user->hasPermission('reportcards.print'), 403);

        $year = AcademicYear::current();
        $sections = $year ? $this->results->visibleSections($user, $year) : collect();
        $section = null;
        if ($request->integer('section')) {
            $section = $sections->firstWhere('id', $request->integer('section'));
            abort_unless($section, 403);
        }
        $section ??= $sections->first();

        $exams = $year ? Exam::query()->where('academic_year_id', $year->id)->ordered()->get() : collect();
        $exam = $request->filled('exam') ? $exams->firstWhere('id', $request->integer('exam')) : null;

        $data = [
            'year' => $year,
            'sections' => $sections,
            'section' => $section,
            'exams' => $exams,
            'exam' => $exam,
            'subjects' => collect(),
            'rows' => collect(),
            'examRows' => collect(),
            'countedExams' => collect(),
            'finalized' => false,
        ];

        if ($section) {
            Gate::authorize('viewResults', [Exam::class, $section]);
            $section->load(['gradeLevel.subjects', 'academicYear', 'classTeacher']);
            $data['subjects'] = $section->gradeLevel->subjects;
            $data['finalized'] = $this->results->isFinalized($section);

            if ($exam) {
                $data['examRows'] = $this->results->examResults($section, $exam);
            } else {
                // نهایی‌شده ← نسخه‌ی ثابت؛ در غیر آن نتیجه‌ی زنده (همان داده‌ی کارنامه)
                $data['rows'] = $this->results->reportCards($section);
                $data['countedExams'] = $this->results->countedExams($section->academic_year_id);
                if ($data['rows']->isNotEmpty()) {
                    // مضامین از خود نتیجه (برای سال نهایی‌شده همان مضامین هنگام نهایی‌کردن)
                    $data['subjects'] = collect($data['rows']->first()['subjects'])->map(fn ($s) => (object) ['id' => $s['id'], 'name' => $s['name']]);
                }
            }
        }

        return $data;
    }

    private function cell($mark): string
    {
        if (! $mark) {
            return '';
        }

        return $mark->is_absent ? __('exams.absent_short') : MarkService::format($mark->mark);
    }
}
