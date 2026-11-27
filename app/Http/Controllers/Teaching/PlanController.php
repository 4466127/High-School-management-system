<?php

namespace App\Http\Controllers\Teaching;

use App\Enums\PlanItemStatus;
use App\Enums\PlanStatus;
use App\Http\Controllers\Controller;
use App\Http\Requests\Teaching\PlanItemRequest;
use App\Models\AcademicYear;
use App\Models\Section;
use App\Models\Subject;
use App\Models\TeacherAssignment;
use App\Models\TeachingPlan;
use App\Models\TeachingPlanItem;
use App\Rules\ValidJalaliDate;
use App\Services\TeachingPlanService;
use App\Support\Digits;
use App\Support\JalaliDate;
use App\Support\Paging;
use App\Support\PersianText;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;
use Illuminate\Validation\Rule;
use Illuminate\View\View;

/**
 * پلان تدریس و پیشرفت (مرحله‌ی ۱۶). فهرست: معلم مضامین و بخش‌های خودش؛ مدیر همه، با فیلتر وضعیت و عقب‌ماندگی.
 * مجوز دقیق در TeachingPlanPolicy. چاپ: ?print=1.
 */
class PlanController extends Controller
{
    public function __construct(private readonly TeachingPlanService $plans) {}

    public function index(Request $request): View
    {
        $user = $request->user();
        $review = Gate::allows('review', TeachingPlan::class);
        abort_unless($review || $user->hasPermission('teaching.plan'), 403);
        $year = AcademicYear::current();

        $assignments = $year ? TeacherAssignment::query()->with(['section.gradeLevel', 'subject', 'teacher'])
            ->whereHas('section', fn ($q) => $q->where('academic_year_id', $year->id))
            ->when(! $review, fn ($q) => $q->where('staff_id', $user->staff_id ?? 0))
            ->get() : collect();
        $plans = $year ? TeachingPlan::with('items')->where('academic_year_id', $year->id)->get()->keyBy(fn ($p) => $p->section_id.'|'.$p->subject_id) : collect();

        $rows = $assignments->map(fn ($a) => ['assignment' => $a, 'plan' => $plan = $plans->get($a->section_id.'|'.$a->subject_id), 'progress' => $plan?->progress()])
            ->sortBy(fn ($r) => [$r['assignment']->section->gradeLevel->sort_order, $r['assignment']->section->name, $r['assignment']->subject->name])->values();

        // خلاصه پیش از فیلتر (کارت‌های بالای صفحه)
        $summary = [
            'total' => $rows->count(),
            'none' => $rows->filter(fn ($r) => ! $r['plan'])->count(),
            'submitted' => $rows->filter(fn ($r) => $r['plan']?->status === PlanStatus::Submitted)->count(),
            'approved' => $rows->filter(fn ($r) => $r['plan']?->status === PlanStatus::Approved)->count(),
            'overdue' => $rows->filter(fn ($r) => ($r['progress']['overdue'] ?? 0) > 0)->count(),
        ];
        $sectionOptions = $rows->map(fn ($r) => $r['assignment']->section)->unique('id')->values();
        $teacherOptions = $rows->map(fn ($r) => $r['assignment']->teacher)->filter()->unique('id')->sortBy('first_name')->values();

        $status = (string) $request->query('status', '');
        $rows = match ($status) {
            'none' => $rows->filter(fn ($r) => ! $r['plan']),
            'overdue' => $rows->filter(fn ($r) => ($r['progress']['overdue'] ?? 0) > 0),
            '' => $rows,
            default => $rows->filter(fn ($r) => $r['plan']?->status->value === $status),
        };
        if ($sectionId = $request->integer('section')) {
            $rows = $rows->filter(fn ($r) => $r['assignment']->section_id === $sectionId);
        }
        if ($review && ($staffId = $request->integer('staff'))) {
            $rows = $rows->filter(fn ($r) => $r['assignment']->staff_id === $staffId);
        }
        // جستجو در نام مضمون و معلم (هر واژه)
        foreach (PersianText::searchTerms($request->query('q')) as $word) {
            $rows = $rows->filter(fn ($r) => str_contains(str_replace(PersianText::ZWNJ, '', $r['assignment']->subject->name.' '.($r['assignment']->teacher?->full_name ?? '')), $word));
        }

        return view('teaching.index', [
            'year' => $year,
            'rows' => Paging::collection($rows->values()),
            'review' => $review,
            'status' => $status,
            'filters' => ['section' => $request->integer('section') ?: null, 'staff' => $request->integer('staff') ?: null, 'q' => (string) $request->query('q', '')],
            'summary' => $summary,
            'sectionOptions' => $sectionOptions,
            'teacherOptions' => $teacherOptions,
            'noStaff' => ! $review && ! $user->staff_id,
        ]);
    }

    public function open(Request $request): RedirectResponse
    {
        // مجوز پیش از اعتبارسنجی؛ محدوده‌ی دقیق (مضمون و بخش خودش) با Policy پایین‌تر
        abort_unless(Gate::allows('review', TeachingPlan::class) || $request->user()->hasPermission('teaching.plan'), 403);
        $data = $request->validate(['section' => ['required', 'integer', 'exists:sections,id'], 'subject' => ['required', 'integer', 'exists:subjects,id']]);
        $section = Section::findOrFail($data['section']);
        $subject = Subject::findOrFail($data['subject']);
        Gate::authorize('open', [TeachingPlan::class, $section, $subject]);

        return redirect()->route('teaching.plans.show', $this->plans->open($section, $subject, $request->user()));
    }

    public function show(Request $request, TeachingPlan $plan): View
    {
        Gate::authorize('view', $plan);
        $plan->load(['section.gradeLevel', 'subject', 'teacher', 'reviewer', 'items']);

        return view('teaching.show', [
            'plan' => $plan,
            'progress' => $plan->progress(),
            'canReview' => Gate::allows('review', TeachingPlan::class),
            'siblings' => TeachingPlan::with('section.gradeLevel')->withCount('items')
                ->where('subject_id', $plan->subject_id)->where('academic_year_id', $plan->academic_year_id)->where('id', '!=', $plan->id)->get()
                ->filter(fn ($p) => $p->items_count > 0),
            'print' => $request->boolean('print'),
        ]);
    }

    public function notes(Request $request, TeachingPlan $plan): RedirectResponse
    {
        Gate::authorize('update', $plan);
        $data = $request->validate(['notes' => ['nullable', 'string', 'max:1000']]);
        $this->plans->updateNotes($plan, $data['notes'] ?? null, $request->user());

        return back()->with('success', __('teaching.saved'));
    }

    public function topics(Request $request, TeachingPlan $plan): RedirectResponse
    {
        Gate::authorize('update', $plan);
        $data = $request->validate([
            'topics' => ['required', 'string', 'max:20000'],
            'start_date' => ['nullable', new ValidJalaliDate],
            'hours' => ['nullable', 'integer', 'min:0', 'max:40'],
        ], [], __('teaching.attributes'));
        $count = $this->plans->addTopics($plan, $data['topics'], JalaliDate::parse($data['start_date'] ?? null)?->toDateString(), (int) ($data['hours'] ?? 0), $request->user());

        return back()->with('success', __('teaching.topics_added', ['count' => Digits::toPersian((string) $count)]));
    }

    public function updateItem(PlanItemRequest $request, TeachingPlanItem $item): RedirectResponse
    {
        Gate::authorize('update', $item->plan);
        $this->plans->updateItem($item, $request->validated(), $request->user());

        return back()->with('success', __('teaching.saved'));
    }

    public function destroyItem(Request $request, TeachingPlanItem $item): RedirectResponse
    {
        Gate::authorize('update', $item->plan);
        $this->plans->removeItem($item, $request->user());

        return back()->with('success', __('teaching.topic_removed'));
    }

    public function progress(Request $request, TeachingPlanItem $item): RedirectResponse
    {
        Gate::authorize('update', $item->plan);
        $data = $request->validate([
            'status' => ['required', Rule::enum(PlanItemStatus::class)],
            'completed_on' => ['nullable', new ValidJalaliDate(notFuture: true)],
            'note' => ['nullable', 'string', 'max:300'],
        ], [], __('teaching.attributes'));
        $this->plans->setProgress($item, PlanItemStatus::from($data['status']), JalaliDate::parse($data['completed_on'] ?? null)?->toDateString(), $data['note'] ?? null, $request->user());

        return back()->with('success', __('teaching.progress_saved'));
    }

    public function submit(Request $request, TeachingPlan $plan): RedirectResponse
    {
        Gate::authorize('update', $plan);
        $this->plans->submit($plan, $request->user());

        return back()->with('success', __('teaching.submitted'));
    }

    public function review(Request $request, TeachingPlan $plan): RedirectResponse
    {
        Gate::authorize('review', TeachingPlan::class);
        $data = $request->validate([
            'decision' => ['required', Rule::in(['approve', 'return'])],
            'note' => ['nullable', 'required_if:decision,return', 'string', 'max:1000'],
        ], ['note.required_if' => __('teaching.return_note_required')], __('teaching.attributes'));

        $data['decision'] === 'approve'
            ? $this->plans->approve($plan, $data['note'] ?? null, $request->user())
            : $this->plans->returnPlan($plan, $data['note'], $request->user());

        return back()->with('success', __($data['decision'] === 'approve' ? 'teaching.approved' : 'teaching.returned'));
    }

    public function copy(Request $request, TeachingPlan $plan): RedirectResponse
    {
        Gate::authorize('update', $plan);
        $data = $request->validate(['from' => ['required', 'integer', 'exists:teaching_plans,id']]);
        $source = TeachingPlan::with('items')->findOrFail($data['from']);
        $count = $this->plans->copyFrom($source, $plan, $request->user());

        return back()->with('success', __('teaching.copied', ['count' => Digits::toPersian((string) $count)]));
    }

    /** برای Blade: آیا موضوع‌ها قابل تغییرند */
    public static function editable(TeachingPlan $plan): bool
    {
        return $plan->status->editable();
    }

    /** @return array<string, string> */
    public static function statusFilters(): array
    {
        return ['none' => __('teaching.filter_none')] + PlanStatus::options() + ['overdue' => __('teaching.filter_overdue')];
    }
}
