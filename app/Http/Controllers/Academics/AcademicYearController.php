<?php

namespace App\Http\Controllers\Academics;

use App\Http\Controllers\Controller;
use App\Http\Requests\Academics\AcademicYearRequest;
use App\Http\Requests\Academics\TermRequest;
use App\Models\AcademicTerm;
use App\Models\AcademicYear;
use App\Services\AcademicYearService;
use Illuminate\Http\RedirectResponse;
use Illuminate\View\View;

/** سال‌های تحصیلی و دوره‌های آن‌ها (مجوز: academics.manage در routes/web.php). */
class AcademicYearController extends Controller
{
    public function __construct(private readonly AcademicYearService $years) {}

    public function index(): View
    {
        return view('academics.years.index', [
            'years' => AcademicYear::query()->withCount(['sections', 'enrollments'])->orderByDesc('start_date')->get(),
        ]);
    }

    public function create(): View
    {
        $last = AcademicYear::query()->orderByDesc('start_date')->first();

        return view('academics.years.create', [
            'year' => new AcademicYear,
            'copyOptions' => AcademicYear::query()->withCount('sections')->orderByDesc('start_date')->get(),
            'defaultCopy' => $last?->id,
        ]);
    }

    public function store(AcademicYearRequest $request): RedirectResponse
    {
        $data = $request->validated();
        $copyFrom = ! empty($data['copy_from']) ? AcademicYear::find($data['copy_from']) : null;

        $year = $this->years->create(
            ['name' => $data['name'], 'start_date' => $data['start_date'], 'end_date' => $data['end_date']],
            $copyFrom,
            (bool) $data['copy_assignments'],
        );

        return redirect()->route('academics.years.edit', $year)->with('success', __('academics.year_created', ['name' => $year->name]));
    }

    public function edit(AcademicYear $year): View
    {
        return view('academics.years.edit', ['year' => $year->load('terms')]);
    }

    public function update(AcademicYearRequest $request, AcademicYear $year): RedirectResponse
    {
        $data = $request->validated();
        $this->years->update($year, ['name' => $data['name'], 'start_date' => $data['start_date'], 'end_date' => $data['end_date']]);

        return redirect()->route('academics.years.edit', $year)->with('success', __('academics.year_updated', ['name' => $year->name]));
    }

    public function setCurrent(AcademicYear $year): RedirectResponse
    {
        $this->years->setCurrent($year);

        return redirect()->route('academics.years.index')->with('success', __('academics.year_now_current', ['name' => $year->name]));
    }

    public function storeTerm(TermRequest $request, AcademicYear $year): RedirectResponse
    {
        $this->years->addTerm($year, $request->validated());

        return redirect()->route('academics.years.edit', $year)->with('success', __('academics.term_saved'));
    }

    public function updateTerm(TermRequest $request, AcademicYear $year, AcademicTerm $term): RedirectResponse
    {
        abort_unless($term->academic_year_id === $year->id, 404);
        $this->years->updateTerm($term, $request->validated());

        return redirect()->route('academics.years.edit', $year)->with('success', __('academics.term_saved'));
    }

    public function destroyTerm(AcademicYear $year, AcademicTerm $term): RedirectResponse
    {
        abort_unless($term->academic_year_id === $year->id, 404);
        $this->years->deleteTerm($term);

        return redirect()->route('academics.years.edit', $year)->with('success', __('academics.term_removed'));
    }
}
