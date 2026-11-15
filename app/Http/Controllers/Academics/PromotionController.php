<?php

namespace App\Http\Controllers\Academics;

use App\Http\Controllers\Controller;
use App\Http\Requests\Academics\PromotionRequest;
use App\Models\AcademicYear;
use App\Models\Section;
use App\Services\EnrollmentService;
use App\Support\Digits;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

/**
 * ارتقای گروهی یک بخش به سال تازه (مجوز: students.manage).
 * گام ۱: انتخاب بخش و سال مقصد؛ گام ۲: تصمیم برای هر دانش‌آموز و بخش‌های مقصد.
 * نتیجه‌ی امتحانات (مرحله‌ی ۶) بعداً تصمیم پیش‌فرض را پیشنهاد می‌کند.
 */
class PromotionController extends Controller
{
    public function __construct(private readonly EnrollmentService $enrollments) {}

    public function create(Request $request): View
    {
        $years = AcademicYear::query()->orderByDesc('start_date')->get();
        $current = AcademicYear::current();
        $sourceSections = $current
            ? Section::query()->with('gradeLevel')->withCount('activeEnrollments')->where('academic_year_id', $current->id)->ordered()->get()
            : collect();

        $section = $sourceSections->firstWhere('id', $request->integer('section'));
        $targetYear = $years->firstWhere('id', $request->integer('target_year'))
            ?? $years->first(fn ($y) => $current && $y->start_date->gt($current->start_date));

        $data = [
            'currentYear' => $current,
            'years' => $years->reject(fn ($y) => $current && $y->id === $current->id),
            'sourceSections' => $sourceSections,
            'section' => $section,
            'targetYear' => $targetYear,
        ];

        if ($section && $targetYear) {
            $nextGrade = $section->gradeLevel->next();
            $targetSections = Section::query()->with('gradeLevel')->where('academic_year_id', $targetYear->id)->ordered()->get();
            $data += [
                'nextGrade' => $nextGrade,
                'enrollments' => $section->activeEnrollments()->with('student')->orderByRaw('roll_no IS NULL, roll_no')->get(),
                'promoteSections' => $nextGrade ? $targetSections->where('grade_level_id', $nextGrade->id)->values() : collect(),
                'repeatSections' => $targetSections->where('grade_level_id', $section->grade_level_id)->values(),
            ];
        }

        return view('academics.promotion.create', $data);
    }

    public function store(PromotionRequest $request): RedirectResponse
    {
        $data = $request->validated();
        $source = Section::findOrFail($data['section_id']);

        abort_unless($source->academic_year_id === AcademicYear::current()?->id, 422, __('academics.promotion_only_current'));

        $summary = $this->enrollments->promote(
            $source,
            AcademicYear::findOrFail($data['target_year_id']),
            array_map('strval', $data['decisions']),
            ! empty($data['promote_section_id']) ? Section::find($data['promote_section_id']) : null,
            ! empty($data['repeat_section_id']) ? Section::find($data['repeat_section_id']) : null,
            $data['date'] ?? null,
        );

        return redirect()->route('academics.sections.show', $source)->with('success', __('academics.promotion_done', array_map(
            fn ($n) => Digits::toPersian($n), $summary,
        )));
    }
}
