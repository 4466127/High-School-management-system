<?php

namespace App\Http\Controllers\Courses;

use App\Enums\CourseStatus;
use App\Http\Controllers\Attendance\Concerns\ResolvesDates;
use App\Http\Controllers\Controller;
use App\Models\Course;
use App\Models\CourseGroup;
use App\Models\CourseSession;
use App\Policies\CoursePolicy;
use App\Services\AttendanceService;
use App\Services\CourseService;
use App\Support\JalaliDate;
use Carbon\CarbonImmutable;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;
use Illuminate\Validation\ValidationException;
use Illuminate\View\View;

/** حاضری کورس: گروه‌های امروز و ورق حاضری هر گروه (معلم: گروه خودش و فقط امروز؛ مسئول کورس‌ها: همه و هر روز). */
class CourseAttendanceController extends Controller
{
    use ResolvesDates;

    public function __construct(private readonly CourseService $courses) {}

    public function index(Request $request): View
    {
        $user = $request->user();
        abort_unless($user->hasPermission('courses.manage') || $user->hasPermission('courses.attendance'), 403);
        $date = $this->dateFrom($request);
        $day = $date->format('Y-m-d');

        $groups = CourseGroup::query()
            ->with(['course', 'teacher'])
            ->withCount('activeParticipants')
            ->where('is_active', true)
            ->whereHas('course', fn ($q) => $q->whereIn('status', [CourseStatus::Active, CourseStatus::Planned])
                ->where('start_date', '<=', $day)
                ->where(fn ($q) => $q->whereNull('end_date')->orWhere('end_date', '>=', $day)))
            ->when(! $user->hasPermission('courses.manage'), fn ($q) => $q->where('staff_id', $user->staff_id ?? 0))
            ->get()
            ->sortBy(fn (CourseGroup $g) => [$g->course->name, $g->shift->value, $g->name])
            ->values();

        $taken = CourseSession::query()->whereIn('course_group_id', $groups->pluck('id')->all() ?: [0])->where('date', $day)->pluck('course_group_id')->all();

        return view('courses.attendance.index', [
            'groups' => $groups, 'taken' => $taken, 'date' => $date, 'today' => AttendanceService::today(),
        ]);
    }

    public function show(Request $request, CourseGroup $group): View
    {
        Gate::authorize('viewAttendance', [Course::class, $group]);
        $group->load(['course', 'teacher']);
        $date = $this->dateFrom($request);
        $session = CourseSession::with(['takenBy', 'updatedBy'])->where('course_group_id', $group->id)->where('date', $date->format('Y-m-d'))->first();
        $blocked = app(CoursePolicy::class)->attendanceBlock($request->user(), $group, $date);

        return view('courses.attendance.sheet', [
            'group' => $group,
            'date' => $date,
            'today' => AttendanceService::today(),
            'session' => $session,
            'participants' => $this->courses->sheetParticipants($group, $session),
            'records' => $session ? $session->records()->get()->keyBy('course_participant_id') : collect(),
            'canRecord' => $blocked === null,
            'blocked' => $blocked,
        ]);
    }

    public function store(Request $request, CourseGroup $group): RedirectResponse
    {
        $data = $request->validate(['date' => ['required', 'date_format:Y-m-d'], 'status' => ['required', 'array']]);
        $date = CarbonImmutable::createFromFormat('!Y-m-d', $data['date'], JalaliDate::TIMEZONE);
        Gate::authorize('recordAttendance', [Course::class, $group, $date]);

        try {
            $result = $this->courses->saveAttendance($group, $date, (array) $request->input('status', []), (array) $request->input('note', []), $request->user());
        } catch (ValidationException $e) {
            return back()->withErrors($e->errors())->withInput();
        }

        return redirect()->route('courses.attendance.show', [$group, 'date' => $date->format('Y-m-d')])
            ->with('success', $result['created'] ? __('courses.attendance_saved') : __('courses.attendance_corrected', ['count' => $result['changed']]));
    }
}
