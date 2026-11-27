<?php

namespace App\Http\Controllers\Students;

use App\Enums\EnrollmentStatus;
use App\Enums\TransferDirection;
use App\Http\Controllers\Controller;
use App\Http\Requests\Students\TransferRequest;
use App\Models\AcademicYear;
use App\Models\BookLoan;
use App\Models\Enrollment;
use App\Models\LetterTemplate;
use App\Models\Student;
use App\Models\StudentTransfer;
use App\Services\StudentRecordService;
use App\Services\TransferService;
use App\Support\Csv;
use App\Support\Digits;
use App\Support\JalaliDate;
use App\Support\Paging;
use App\Support\PersianText;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\View\View;
use Symfony\Component\HttpFoundation\StreamedResponse;

/** انتقال میان مکاتب، سند انتقال، سوابق تحصیلی و فایل سوابق (مرحله‌ی ۱۳؛ students.manage). */
class TransferController extends Controller
{
    public function __construct(
        private readonly TransferService $transfers,
        private readonly StudentRecordService $records,
    ) {}

    public function index(Request $request): View
    {
        return view('students.transfers.index', [
            'transfers' => $this->query($request)->with('student')->paginate(Paging::size())->withQueryString(),
            'filters' => $this->filters($request),
        ]);
    }

    public function export(Request $request): StreamedResponse
    {
        $rows = $this->query($request)->with('student')->get()->map(fn (StudentTransfer $t) => [
            $t->direction->label(), Digits::toLatin(JalaliDate::format($t->transfer_date)), $t->student->admission_no, $t->student->full_name,
            $t->student->father_name, $t->other_school, $t->other_province, $t->reason, $t->reference_number,
            $t->isCancelled() ? __('transfers.cancelled') : '',
        ]);

        return Csv::download('transfers.csv', [
            __('transfers.direction'), __('transfers.transfer_date'), __('students.admission_no'), __('students.full_name'),
            __('students.father_name'), __('transfers.other_school'), __('transfers.other_province'), __('transfers.reason'),
            __('transfers.reference_number'), __('transfers.status'),
        ], $rows);
    }

    public function create(Request $request, Student $student): View
    {
        $incoming = $request->routeIs('student-transfers.in.*');
        $enrollment = Enrollment::query()->with('section.gradeLevel')->where('student_id', $student->id)
            ->where('academic_year_id', AcademicYear::current()?->id ?? 0)->where('status', EnrollmentStatus::Active)->first();

        return view('students.transfers.form', [
            'student' => $student,
            'incoming' => $incoming,
            'enrollment' => $enrollment,
            'outstanding' => $incoming ? 0 : $this->records->outstandingCents($student),
            // کتاب‌های امانت کتابخانه (مرحله‌ی ۱۷) — فقط هشدار
            'libraryLoans' => $incoming ? collect() : BookLoan::active()->with('copy.book')->where('student_id', $student->id)->get(),
        ]);
    }

    public function store(TransferRequest $request, Student $student): RedirectResponse
    {
        if ($request->isIncoming()) {
            $package = $request->hasFile('package') ? $this->records->parsePackage((string) $request->file('package')->get()) : null;
            $transfer = $this->transfers->transferIn($student, $request->transferData(), $request->user(), $package);
        } else {
            $transfer = $this->transfers->transferOut($student, $request->transferData(), $request->user());
        }

        return redirect()->route('student-transfers.show', $transfer)->with('success', __('transfers.saved'));
    }

    public function show(StudentTransfer $transfer): View
    {
        $transfer->load(['student', 'enrollment.section.gradeLevel', 'creator', 'canceller']);

        return view('students.transfers.show', [
            'transfer' => $transfer,
            'letterTemplate' => LetterTemplate::active()->where('type', 'transfer')->first(),
        ]);
    }

    public function cancel(Request $request, StudentTransfer $transfer): RedirectResponse
    {
        $data = $request->validate(['reason' => ['required', 'string', 'max:500']], [], ['reason' => __('transfers.cancel_reason')]);
        $this->transfers->cancel($transfer, $data['reason'], $request->user());

        return back()->with('success', __('transfers.cancelled_msg'));
    }

    /** چاپ سوابق تحصیلی؛ با transfer = سند انتقال */
    public function record(Request $request, Student $student): View
    {
        $transfer = $request->integer('transfer')
            ? StudentTransfer::where('student_id', $student->id)->where('direction', TransferDirection::Out)->findOrFail($request->integer('transfer'))
            : null;

        return view('students.record', [
            'student' => $student,
            'history' => $this->records->history($student),
            'past' => $student->pastResults()->active()->get(),
            'transfer' => $transfer,
            'incoming' => $student->transfers()->where('direction', TransferDirection::In)->where('status', 'completed')->get(),
        ]);
    }

    /** فایل سوابق (JSON) برای مکتب مقصد */
    public function package(Request $request, Student $student): Response
    {
        $transfer = $student->transfers()->where('direction', TransferDirection::Out)->where('status', 'completed')->first();
        $data = $this->records->package($student, $request->user(), $transfer ? [
            'to_school' => $transfer->other_school,
            'transfer_date' => $transfer->transfer_date->toDateString(),
            'reason' => $transfer->reason,
            'reference_number' => $transfer->reference_number,
        ] : null);

        return response(json_encode($data, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES), 200, [
            'Content-Type' => 'application/json; charset=utf-8',
            'Content-Disposition' => 'attachment; filename="student-record-'.preg_replace('/[^A-Za-z0-9_-]/', '', (string) $student->admission_no).'.json"',
        ]);
    }

    private function filters(Request $request): array
    {
        return [
            'q' => (string) $request->query('q', ''),
            'direction' => (string) $request->query('direction', ''),
            'from' => (string) $request->query('from', ''),
            'to' => (string) $request->query('to', ''),
        ];
    }

    private function query(Request $request)
    {
        $from = JalaliDate::parse($request->query('from'));
        $to = JalaliDate::parse($request->query('to'));
        $terms = PersianText::searchTerms($request->query('q'));

        return StudentTransfer::query()
            ->when(TransferDirection::tryFrom((string) $request->query('direction')), fn ($q, $d) => $q->where('direction', $d))
            ->when($from, fn ($q) => $q->where('transfer_date', '>=', $from->toDateString()))
            ->when($to, fn ($q) => $q->where('transfer_date', '<=', $to->toDateString()))
            ->when($terms !== [], fn ($q) => $q->where(fn ($w) => $w
                ->whereHas('student', fn ($s) => $s->withTrashed()->search($request->query('q')))
                ->orWhere(fn ($o) => PersianText::whereMatches($o, ['other_school'], $request->query('q')))))
            ->orderByDesc('transfer_date')->orderByDesc('id');
    }
}
