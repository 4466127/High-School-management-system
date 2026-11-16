<?php

namespace App\Http\Controllers\Courses;

use App\Enums\CourseSeason;
use App\Enums\CourseStatus;
use App\Enums\EmploymentStatus;
use App\Enums\Shift;
use App\Http\Controllers\Controller;
use App\Http\Requests\Courses\CourseRequest;
use App\Models\AcademicYear;
use App\Models\Course;
use App\Models\CourseGroup;
use App\Models\Enrollment;
use App\Models\Section;
use App\Models\Staff;
use App\Services\CourseLedgerService;
use App\Services\CourseService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Gate;
use Illuminate\Validation\Rule;
use Illuminate\View\View;

/** فهرست کورس‌ها، ثبت و ویرایش، صفحه‌ی کورس (گروه‌ها، اشتراک‌کنندگان، حاضری، خلاصه‌ی مالی). */
class CourseController extends Controller
{
    public function __construct(
        private readonly CourseService $courses,
        private readonly CourseLedgerService $ledger,
    ) {}

    public function index(Request $request): View
    {
        Gate::authorize('viewAny', Course::class);
        $user = $request->user();
        $status = CourseStatus::tryFrom((string) $request->query('status'));
        $season = CourseSeason::tryFrom((string) $request->query('season'));
        $seeAll = $user->hasPermission('courses.manage') || $user->hasPermission('courses.ledger');

        $courses = Course::query()
            ->withCount(['groups', 'participants as active_participants_count' => fn ($q) => $q->where('course_participants.status', 'active')])
            ->when(! $seeAll, fn ($q) => $q->taughtBy($user->staff_id))
            ->when($status, fn ($q) => $q->where('status', $status))
            ->when($season, fn ($q) => $q->where('season', $season))
            ->orderByRaw("FIELD(status, 'active', 'planned', 'finished', 'cancelled')")
            ->orderByDesc('start_date')
            ->get();

        $canLedger = $user->hasPermission('courses.ledger');

        return view('courses.index', [
            'courses' => $courses,
            'summaries' => $canLedger ? $this->ledger->summaries($courses->pluck('id')->all()) : collect(),
            'canLedger' => $canLedger,
            'filters' => ['status' => $status?->value, 'season' => $season?->value],
        ]);
    }

    public function create(): View
    {
        Gate::authorize('manage', Course::class);

        return view('courses.form', ['course' => new Course(['season' => CourseSeason::Winter, 'status' => CourseStatus::Planned])]);
    }

    public function store(CourseRequest $request): RedirectResponse
    {
        Gate::authorize('manage', Course::class);
        $course = $this->courses->saveCourse(null, $request->validated(), $request->user());

        return redirect()->route('courses.list.show', $course)->with('success', __('courses.saved', ['name' => $course->name]));
    }

    public function edit(Course $course): View
    {
        Gate::authorize('manage', Course::class);

        return view('courses.form', ['course' => $course]);
    }

    public function update(CourseRequest $request, Course $course): RedirectResponse
    {
        Gate::authorize('manage', Course::class);
        $this->courses->saveCourse($course, $request->validated(), $request->user());

        return redirect()->route('courses.list.show', $course)->with('success', __('courses.saved', ['name' => $course->name]));
    }

    public function show(Request $request, Course $course): View
    {
        Gate::authorize('view', $course);
        $user = $request->user();
        $course->load(['groups.teacher', 'groups.participants.student', 'creator']);

        $data = [
            'course' => $course,
            'canManage' => $user->can('manage', Course::class),
            'canLedger' => $user->can('ledger', Course::class),
            'attendance' => $this->courses->attendanceTotals($course),
            'summary' => $user->can('ledger', Course::class) ? $this->ledger->summary($course) : null,
            'teachers' => collect(),
            'sections' => collect(),
            'sectionStudents' => collect(),
            'pickSection' => null,
        ];

        if ($data['canManage']) {
            $data['teachers'] = $this->teacherOptions();
            [$data['sections'], $data['pickSection'], $data['sectionStudents']] = $this->studentPicker($request, $course);
        }

        return view('courses.show', $data);
    }

    public function storeGroup(Request $request, Course $course): RedirectResponse
    {
        Gate::authorize('manage', Course::class);
        $group = $this->courses->saveGroup($course, null, $this->groupData($request, $course), $request->user());

        return redirect()->to(route('courses.list.show', $course).'#groups')->with('success', __('courses.group_saved', ['name' => $group->name]));
    }

    public function updateGroup(Request $request, CourseGroup $group): RedirectResponse
    {
        Gate::authorize('manage', Course::class);
        $this->courses->saveGroup($group->course, $group, $this->groupData($request, $group->course, $group), $request->user());

        return redirect()->to(route('courses.list.show', $group->course_id).'#groups')->with('success', __('courses.group_saved', ['name' => $group->name]));
    }

    private function groupData(Request $request, Course $course, ?CourseGroup $group = null): array
    {
        $data = $request->validate([
            'name' => ['required', 'string', 'max:100', Rule::unique('course_groups', 'name')->where('course_id', $course->id)->ignore($group?->id)],
            'shift' => ['required', Rule::enum(Shift::class)],
            'staff_id' => ['nullable', 'integer', Rule::exists('staff', 'id')->whereNull('deleted_at')],
            'schedule' => ['nullable', 'string', 'max:150'],
            'is_active' => ['nullable', 'boolean'],
        ], [], __('courses.attributes'));
        $data['is_active'] = $request->boolean('is_active', $group === null);

        return $data;
    }

    /** معلمان و کارکنان برسر وظیفه (معلم کورس می‌تواند کارمند مکتب یا معلم بیرونی ثبت‌شده در کارکنان باشد) */
    private function teacherOptions(): Collection
    {
        return Staff::query()
            ->where('employment_status', '!=', EmploymentStatus::Left)
            ->orderBy('first_name')->orderBy('last_name')
            ->get(['id', 'first_name', 'last_name', 'staff_type']);
    }

    /** انتخاب دانش‌آموزان مکتب از یک بخش سال جاری برای شامل کردن در گروه */
    private function studentPicker(Request $request, Course $course): array
    {
        $year = AcademicYear::current();
        if (! $year) {
            return [collect(), null, collect()];
        }
        $sections = Section::query()->with('gradeLevel')->where('academic_year_id', $year->id)->ordered()->get();
        $section = $request->integer('pick') ? $sections->firstWhere('id', $request->integer('pick')) : null;
        if (! $section) {
            return [$sections, null, collect()];
        }

        $inCourse = $course->groups->flatMap->participants->pluck('student_id')->filter()->all();
        $students = Enrollment::query()
            ->with('student')
            ->where('section_id', $section->id)
            ->where('status', 'active')
            ->orderByRaw('roll_no IS NULL, roll_no')
            ->get()
            ->map(fn (Enrollment $e) => ['enrollment' => $e, 'in_course' => in_array($e->student_id, $inCourse, true)]);

        return [$sections, $section, $students];
    }
}
