<?php

namespace App\Http\Controllers\Timetable;

use App\Enums\EmploymentStatus;
use App\Enums\StaffType;
use App\Http\Controllers\Controller;
use App\Http\Requests\Timetable\TimetableCellRequest;
use App\Models\AcademicYear;
use App\Models\Period;
use App\Models\Room;
use App\Models\Section;
use App\Models\Staff;
use App\Models\User;
use App\Services\TimetableService;
use App\Support\Digits;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Collection;
use Illuminate\View\View;

/**
 * تقسیم اوقات هر بخش (مرحله‌ی ۱۵). دیدن: timetable.manage یا academics.view؛ همه‌ی بخش‌ها با timetable.manage یا students.view_all، معلم فقط بخش‌های خودش.
 * تغییر خانه‌ها و کاپی: فقط timetable.manage. چاپ: ?print=1؛ چاپ همه‌ی بخش‌ها: ?all=1&print=1.
 */
class TimetableController extends Controller
{
    public function __construct(private readonly TimetableService $timetable) {}

    public function index(Request $request): View
    {
        $user = $request->user();
        abort_unless(self::canManage($user) || $user->hasPermission('academics.view'), 403);
        $year = AcademicYear::current();
        $sections = $year ? $this->visibleSections($user, $year) : collect();

        $print = $request->boolean('print');
        if ($request->boolean('all') && self::canManage($user)) {
            return view('timetable.print-all', [
                'year' => $year,
                'items' => $sections->map(fn (Section $s) => [
                    'section' => $s, 'periods' => $this->timetable->periods($s->shift), 'grid' => $this->timetable->sectionGrid($s),
                ]),
                'days' => $this->timetable->days(),
            ]);
        }

        $section = $request->integer('section') ? $sections->firstWhere('id', $request->integer('section')) : $sections->first();
        abort_if($request->integer('section') && ! $section, 403);

        $data = [
            'year' => $year,
            'sections' => $sections,
            'section' => $section,
            'days' => $this->timetable->days(),
            'canManage' => self::canManage($user),
            'print' => $print,
        ];
        if ($section) {
            $section->loadMissing('gradeLevel.subjects', 'classTeacher');
            $grid = $this->timetable->sectionGrid($section);
            $data += [
                'periods' => $this->timetable->periods($section->shift),
                'grid' => $grid,
                'hours' => $this->timetable->subjectHours($grid),
                'defaults' => $this->timetable->defaultTeachers($section),
                'teachers' => self::teachers(),
                'rooms' => Room::where('is_active', true)->orderBy('name')->get(),
                'siblings' => $sections->filter(fn ($s) => $s->id !== $section->id && $s->grade_level_id === $section->grade_level_id && $s->shift === $section->shift)->values(),
            ];
        }

        return view('timetable.section', $data);
    }

    public function cell(TimetableCellRequest $request, Section $section): RedirectResponse
    {
        $data = $request->validated();
        $period = Period::findOrFail($data['period_id']);
        $back = redirect()->route('timetable.sections.index', ['section' => $section->id]);

        if ($request->boolean('clear') || empty($data['subject_id'])) {
            $this->timetable->clearCell($section, $data['day'], $period, $request->user());

            return $back->with('success', __('timetable.cleared'));
        }
        $this->timetable->saveCell($section, $data['day'], $period, $data, $request->user());

        return $back->with('success', __('timetable.saved'));
    }

    public function copy(Request $request, Section $section): RedirectResponse
    {
        $data = $request->validate(['from' => ['required', 'integer', 'exists:sections,id']], [], ['from' => __('timetable.copy_from')]);
        $count = $this->timetable->copySubjects(Section::findOrFail($data['from']), $section, $request->user());

        return redirect()->route('timetable.sections.index', ['section' => $section->id])
            ->with('success', __('timetable.copied', ['count' => Digits::toPersian((string) $count)]));
    }

    public static function canManage(User $user): bool
    {
        return $user->hasPermission('timetable.manage');
    }

    /** معلمان فعال (برای انتخاب در خانه) */
    public static function teachers(): Collection
    {
        return Staff::query()
            ->whereIn('staff_type', [StaffType::Teacher, StaffType::CourseTeacher])
            ->where('employment_status', EmploymentStatus::Active)
            ->orderBy('first_name')->get();
    }

    private function visibleSections(User $user, AcademicYear $year): Collection
    {
        $all = self::canManage($user) || $user->hasPermission('students.view_all');

        return Section::query()->with('gradeLevel')
            ->where('academic_year_id', $year->id)
            ->when(! $all, fn ($q) => $q->whereIn('sections.id', $user->taughtSectionIds() ?: [0]))
            ->ordered()->get();
    }
}
