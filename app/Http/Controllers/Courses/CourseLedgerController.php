<?php

namespace App\Http\Controllers\Courses;

use App\Enums\CourseChannel;
use App\Enums\CourseEntryType;
use App\Http\Controllers\Controller;
use App\Models\Course;
use App\Models\CourseLedgerEntry;
use App\Models\Staff;
use App\Rules\ValidAmount;
use App\Rules\ValidJalaliDate;
use App\Services\CourseLedgerService;
use App\Services\FinanceService;
use App\Support\Csv;
use App\Support\Digits;
use App\Support\JalaliDate;
use App\Support\Money;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;
use Illuminate\View\View;
use Symfony\Component\HttpFoundation\StreamedResponse;

/** مالی کورس‌ها: خلاصه‌ی همه‌ی کورس‌ها، دفتر هر کورس (کمک، معاش، مصرف)، ثبت، باطل، چاپ و CSV (مجوز: courses.ledger). */
class CourseLedgerController extends Controller
{
    public function __construct(private readonly CourseLedgerService $ledger) {}

    public function index(): View
    {
        Gate::authorize('ledger', Course::class);
        $courses = Course::query()->orderByRaw("FIELD(status, 'active', 'planned', 'finished', 'cancelled')")->orderByDesc('start_date')->get();
        $summaries = $this->ledger->summaries($courses->pluck('id')->all());

        $totals = ['donations_total' => 0, 'donations_direct' => 0, 'fees' => 0, 'salaries' => 0, 'expenses' => 0, 'balance' => 0];
        foreach ($summaries as $s) {
            foreach ($totals as $key => $_) {
                $totals[$key] += $s[$key];
            }
        }

        return view('courses.ledger.index', ['courses' => $courses, 'summaries' => $summaries, 'totals' => $totals]);
    }

    public function show(Request $request, Course $course): View
    {
        return view('courses.ledger.show', $this->load($request, $course) + ['print' => $request->boolean('print')]);
    }

    public function export(Request $request, Course $course): StreamedResponse
    {
        $rows = $this->load($request, $course)['entries']->map(fn (CourseLedgerEntry $e) => [
            Digits::toLatin(JalaliDate::format($e->date)), $e->entry_type->label(), $e->channel->label(), Money::plain($e->amount),
            $e->donor_name ?? ($e->entry_type->isIncome() ? __('courses.anonymous') : ''), $e->teacher?->full_name,
            $e->description, $e->recorder?->name, $e->isVoided() ? __('finance.voided').': '.$e->void_reason : '',
        ]);

        return Csv::download("course-{$course->id}-ledger.csv", [
            __('finance.date'), __('courses.entry_type'), __('courses.channel'), __('finance.amount'), __('courses.donor'),
            __('courses.teacher'), __('finance.description'), __('finance.recorded_by'), __('finance.status'),
        ], $rows);
    }

    public function store(Request $request, Course $course): RedirectResponse
    {
        Gate::authorize('ledger', Course::class);
        $data = $request->validate([
            'entry_type' => ['required', Rule::enum(CourseEntryType::class)],
            'channel' => ['required', Rule::enum(CourseChannel::class)],
            'staff_id' => ['nullable', 'integer', Rule::exists('staff', 'id')],
            'amount' => ['required', new ValidAmount],
            'date' => ['required', new ValidJalaliDate(notFuture: true)],
            'donor_name' => ['nullable', 'string', 'max:150'],
            'description' => ['nullable', 'string', 'max:500'],
        ], [], __('courses.attributes'));

        $type = CourseEntryType::from($data['entry_type']);
        $channel = $type === CourseEntryType::Donation ? CourseChannel::from($data['channel']) : CourseChannel::Cashbox;
        $staffId = $type->needsTeacher($channel) ? ($data['staff_id'] ?? null) : null;
        $errors = [];
        if ($type !== CourseEntryType::Donation && $data['channel'] === CourseChannel::DirectToTeacher->value) {
            $errors['channel'] = __('courses.direct_only_donation');
        }
        if ($type->needsTeacher($channel) && ! in_array($staffId, $this->courseTeacherIds($course), true)) {
            $errors['staff_id'] = __('courses.teacher_required');
        }
        if ($errors !== []) {
            return back()->withErrors($errors)->withInput();
        }

        try {
            $this->ledger->record($course, [
                'entry_type' => $type,
                'channel' => $channel,
                'staff_id' => $staffId,
                'amount_cents' => FinanceService::parseAmount($data['amount']),
                'date' => JalaliDate::parse($data['date'])->toDateString(),
                'donor_name' => $type->isIncome() ? ($data['donor_name'] ?? null) : null,
                'description' => $data['description'] ?? null,
            ], $request->user());
        } catch (ValidationException $e) {
            return back()->withErrors($e->errors())->withInput();
        }

        return redirect()->route('courses.ledger.show', $course)->with('success', __('courses.entry_saved'));
    }

    public function void(Request $request, CourseLedgerEntry $entry): RedirectResponse
    {
        Gate::authorize('ledger', Course::class);
        $data = $request->validate(['reason' => ['required', 'string', 'max:500']], [], ['reason' => __('students.reason')]);

        try {
            $this->ledger->void($entry, $data['reason'], $request->user());
        } catch (ValidationException $e) {
            return back()->with('error', collect($e->errors())->flatten()->first());
        }

        return redirect()->route('courses.ledger.show', $entry->course_id)->with('success', __('courses.entry_voided'));
    }

    /** @return list<int> */
    private function courseTeacherIds(Course $course): array
    {
        return array_map('intval', $course->groups()->whereNotNull('staff_id')->pluck('staff_id')->unique()->values()->all());
    }

    private function load(Request $request, Course $course): array
    {
        Gate::authorize('ledger', Course::class);
        $type = CourseEntryType::tryFrom((string) $request->query('type'));
        $showVoided = $request->boolean('voided');

        $entries = CourseLedgerEntry::query()
            ->with(['teacher', 'recorder'])
            ->where('course_id', $course->id)
            ->when($type, fn ($q) => $q->where('entry_type', $type))
            ->when(! $showVoided, fn ($q) => $q->whereNull('voided_at'))
            ->orderByDesc('date')->orderByDesc('id')
            ->get();

        $summary = $this->ledger->summary($course);

        return [
            'course' => $course,
            'entries' => $entries,
            'summary' => $summary,
            'teacherNames' => Staff::withTrashed()->whereIn('id', array_keys($summary['teachers']) ?: [0])->get()->mapWithKeys(fn (Staff $s) => [$s->id => $s->full_name]),
            'teachers' => Staff::withTrashed()->whereIn('id', $this->courseTeacherIds($course) ?: [0])->orderBy('first_name')->get(),
            'filters' => ['type' => $type?->value, 'voided' => $showVoided],
        ];
    }
}
