<?php

namespace App\Http\Controllers\Labs;

use App\Enums\ActivityStatus;
use App\Enums\EnrollmentStatus;
use App\Http\Controllers\Controller;
use App\Http\Requests\Labs\ActivityRequest;
use App\Models\AcademicYear;
use App\Models\Enrollment;
use App\Models\Lab;
use App\Models\LabItem;
use App\Models\Period;
use App\Models\PracticalActivity;
use App\Models\Section;
use App\Models\Subject;
use App\Models\TeacherAssignment;
use App\Services\AttendanceService;
use App\Services\LabService;
use App\Support\Paging;
use App\Support\Quantity;
use Closure;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;
use Illuminate\View\View;

/**
 * فعالیت‌های عملی لابراتوار (مرحله‌ی ۱۸). مجوز در PracticalActivityPolicy:
 * دیدن فهرست: labs.view؛ پلان و ثبت: معلم برای مضامین خودش، مسئول لابراتوار و مدیر برای همه. چاپ ورق فعالیت: ?print=1.
 */
class ActivityController extends Controller
{
    public function __construct(private readonly LabService $labs) {}

    public function index(Request $request): View
    {
        Gate::authorize('viewAny', PracticalActivity::class);
        $user = $request->user();
        $year = AcademicYear::current();
        $today = AttendanceService::today()->toDateString();
        $status = (string) $request->query('status', 'upcoming');
        $mine = $request->boolean('mine');

        $query = PracticalActivity::query()->with(['lab', 'section.gradeLevel', 'subject', 'teacher', 'period'])->withCount(['students as present_count' => fn ($q) => $q->where('present', true)])
            ->where('academic_year_id', $year?->id ?? 0)
            ->when((int) $request->query('lab'), fn ($q, $lab) => $q->where('lab_id', $lab))
            ->when((int) $request->query('section'), fn ($q, $s) => $q->where('section_id', $s))
            ->when($mine, fn ($q) => $q->where('staff_id', $user->staff_id ?? 0));
        match ($status) {
            'pending' => $query->where('status', ActivityStatus::Planned->value)->where('activity_date', '<', $today)->orderByDesc('activity_date'),
            'done' => $query->where('status', ActivityStatus::Done->value)->orderByDesc('activity_date'),
            'cancelled' => $query->where('status', ActivityStatus::Cancelled->value)->orderByDesc('activity_date'),
            'all' => $query->orderByDesc('activity_date'),
            default => $query->where('status', ActivityStatus::Planned->value)->where('activity_date', '>=', $today)->orderBy('activity_date'),
        };

        return view('labs.activities.index', [
            'year' => $year,
            'activities' => $query->orderBy('period_id')->paginate(Paging::size())->withQueryString(),
            'labs' => Lab::orderBy('name')->get(),
            'sections' => $year ? Section::ordered()->where('academic_year_id', $year->id)->get() : collect(),
            'filters' => $request->only(['lab', 'section', 'status', 'mine']) + ['status' => $status],
            'canCreate' => Gate::allows('create', PracticalActivity::class),
            'isTeacher' => (bool) $user->staff_id,
        ]);
    }

    public function create(Request $request): View
    {
        Gate::authorize('create', PracticalActivity::class);

        return view('labs.activities.form', $this->formData($request, new PracticalActivity([
            'lab_id' => (int) $request->query('lab') ?: null, 'activity_date' => AttendanceService::today()->toDateString(),
        ])));
    }

    public function store(ActivityRequest $request): RedirectResponse
    {
        [$sectionId, $subjectId] = array_map('intval', explode('|', $request->validated()['assignment']));
        $section = Section::findOrFail($sectionId);
        $subject = Subject::findOrFail($subjectId);
        Gate::authorize('create', [PracticalActivity::class, $section, $subject]);
        $activity = $this->labs->planActivity($section, $subject, $request->activityData(), $request->user());

        return redirect()->route('labs.activities.show', $activity)->with('success', __('labs.activity_planned'));
    }

    public function show(Request $request, PracticalActivity $activity): View
    {
        Gate::authorize('view', $activity);
        $activity->load(['lab', 'section.gradeLevel', 'subject', 'teacher', 'period', 'materials.item', 'students']);
        $records = $activity->students->keyBy('enrollment_id');
        $enrollments = Enrollment::with('student')->where('section_id', $activity->section_id)
            ->where(fn ($q) => $q->where('status', EnrollmentStatus::Active)->orWhereIn('id', $records->keys()))
            ->orderBy('roll_no')->orderBy('id')->get();

        return view('labs.activities.show', [
            'activity' => $activity,
            'enrollments' => $enrollments,
            'records' => $records,
            'canUpdate' => Gate::allows('update', $activity),
            'items' => LabItem::where('lab_id', $activity->lab_id)->where('is_active', true)->where('quantity', '>', 0)->orderBy('name')->get(),
            'print' => $request->boolean('print'),
        ]);
    }

    public function edit(Request $request, PracticalActivity $activity): View
    {
        Gate::authorize('update', $activity);

        return view('labs.activities.form', $this->formData($request, $activity));
    }

    public function update(ActivityRequest $request, PracticalActivity $activity): RedirectResponse
    {
        Gate::authorize('update', $activity);
        $this->labs->updateActivity($activity, $request->activityData(), $request->user());

        return redirect()->route('labs.activities.show', $activity)->with('success', __('labs.saved'));
    }

    public function result(Request $request, PracticalActivity $activity): RedirectResponse
    {
        Gate::authorize('update', $activity);
        $data = $request->validate([
            'rows' => ['nullable', 'array'],
            'rows.*.present' => ['nullable', 'boolean'],
            'rows.*.score' => ['nullable', 'numeric', 'min:0', 'max:100'],
            'rows.*.note' => ['nullable', 'string', 'max:200'],
            'result_note' => ['nullable', 'string', 'max:1000'],
        ], [], __('labs.attributes'));
        $this->labs->recordResult($activity, $data['rows'] ?? [], $data['result_note'] ?? null, $request->user());

        return back()->with('success', __('labs.result_saved'));
    }

    public function cancel(Request $request, PracticalActivity $activity): RedirectResponse
    {
        Gate::authorize('update', $activity);
        $data = $request->validate(['reason' => ['required', 'string', 'max:300']], [], __('labs.attributes'));
        $this->labs->cancelActivity($activity, $data['reason'], $request->user());

        return back()->with('success', __('labs.activity_cancelled'));
    }

    public function material(Request $request, PracticalActivity $activity): RedirectResponse
    {
        Gate::authorize('update', $activity);
        $data = $request->validate([
            'item_id' => ['required', 'integer', 'exists:lab_items,id'],
            'amount' => ['required', fn (string $a, mixed $v, Closure $fail) => Quantity::parse((string) $v) === null ? $fail(__('labs.quantity_invalid')) : null],
        ], [], __('labs.attributes'));
        $this->labs->useMaterial($activity, LabItem::findOrFail($data['item_id']), Quantity::parse((string) $data['amount']), $request->user());

        return back()->with('success', __('labs.material_saved'));
    }

    /** @return array<string, mixed> */
    private function formData(Request $request, PracticalActivity $activity): array
    {
        $user = $request->user();
        $year = AcademicYear::current();
        $all = Gate::allows('manage', PracticalActivity::class);
        $assignments = $year ? TeacherAssignment::query()->with(['section.gradeLevel', 'subject', 'teacher'])
            ->whereHas('section', fn ($q) => $q->where('academic_year_id', $year->id))
            ->when(! $all, fn ($q) => $q->where('staff_id', $user->staff_id ?? 0))
            ->get()->sortBy(fn ($a) => [$a->section->gradeLevel->sort_order, $a->section->name, $a->subject->name])
            ->mapWithKeys(fn ($a) => [$a->section_id.'|'.$a->subject_id => $a->subject->name.' — '.$a->section->gradeLevel->name.' '.$a->section->name.($all && $a->teacher ? ' ('.$a->teacher->full_name.')' : '')])
            ->all() : [];

        return [
            'activity' => $activity,
            'assignments' => $assignments,
            'labs' => Lab::active()->orderBy('name')->pluck('name', 'id')->all(),
            'periods' => Period::query()->where('is_active', true)->where('is_break', false)->ordered()->get()
                ->mapWithKeys(fn ($p) => [$p->id => $p->name.' ('.$p->shift->label().' · '.$p->timeRange().')'])->all(),
        ];
    }
}
