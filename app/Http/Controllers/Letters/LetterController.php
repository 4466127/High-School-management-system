<?php

namespace App\Http\Controllers\Letters;

use App\Enums\LetterDirection;
use App\Enums\LetterStatus;
use App\Enums\LetterType;
use App\Http\Controllers\Controller;
use App\Http\Requests\Letters\IncomingLetterRequest;
use App\Http\Requests\Letters\LetterRequest;
use App\Models\Letter;
use App\Models\LetterAttachment;
use App\Models\LetterTemplate;
use App\Models\Staff;
use App\Models\Student;
use App\Services\LetterService;
use App\Support\Csv;
use App\Support\Digits;
use App\Support\JalaliDate;
use App\Support\Paging;
use App\Support\PrivateFiles;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;
use Symfony\Component\HttpFoundation\StreamedResponse;

/** مکاتیب صادره و وارده (مرحله‌ی ۱۲؛ مجوز letters.manage روی همه‌ی مسیرها). منطق در LetterService. */
class LetterController extends Controller
{
    public function __construct(private readonly LetterService $letters) {}

    // ---------- فهرست و بایگانی ----------

    public function outgoing(Request $request): View
    {
        return $this->index($request, LetterDirection::Outgoing);
    }

    public function incoming(Request $request): View
    {
        return $this->index($request, LetterDirection::Incoming);
    }

    public function export(Request $request): StreamedResponse
    {
        $direction = LetterDirection::tryFrom((string) $request->query('direction')) ?? LetterDirection::Outgoing;
        $rows = $this->query($request, $direction)->get()->map(fn (Letter $l) => [
            $l->number, Digits::toLatin(JalaliDate::format($l->letter_date)), $l->type->label(), $l->status->label(),
            $l->recipient, $l->organization, $l->subject, $l->external_number,
            $l->student?->full_name, $l->staff?->full_name, $l->cancel_reason,
        ]);

        return Csv::download('letters-'.$direction->value.'.csv', [
            __('letters.number'), __('letters.date'), __('letters.type'), __('letters.status'),
            __($direction === LetterDirection::Outgoing ? 'letters.recipient' : 'letters.sender'), __('letters.organization'),
            __('letters.subject'), __('letters.external_number'), __('letters.student'), __('letters.staff'), __('letters.cancel_reason'),
        ], $rows);
    }

    // ---------- صادره ----------

    public function create(Request $request): View
    {
        $template = $request->integer('template') ? LetterTemplate::active()->find($request->integer('template')) : null;
        $student = $this->findStudent($request->query('student'));
        $staff = $request->integer('staff') ? Staff::find($request->integer('staff')) : null;
        $replyTo = $request->integer('reply_to') ? Letter::where('direction', LetterDirection::Incoming)->find($request->integer('reply_to')) : null;

        // از صفحه‌ی انتقال (مرحله‌ی ۱۳): مکتب مقصد، تاریخ و دلیل انتقال
        $transfer = $student && $request->integer('transfer') ? $student->transfers()->find($request->integer('transfer')) : null;
        $extra = $transfer ? [
            'other_school' => $transfer->other_school,
            'transfer_date' => JalaliDate::format($transfer->transfer_date, 'long'),
            'transfer_reason' => $transfer->reason,
            'organization' => $transfer->other_school,
        ] : [];

        $draft = $this->letters->draftFromTemplate($template, $student, $staff, $extra);
        if ($transfer) {
            $draft['organization'] = $transfer->other_school;
        }
        if ($replyTo) {
            $draft += ['reply_to_id' => $replyTo->id, 'recipient' => $replyTo->recipient, 'organization' => $replyTo->organization];
            $draft['subject'] = $draft['subject'] ?: __('letters.reply_subject', ['subject' => $replyTo->subject]);
        }

        return view('letters.create', [
            'letter' => new Letter($draft + ['letter_date' => now()]),
            'templates' => LetterTemplate::active()->get(),
            'staffList' => Staff::query()->orderBy('first_name')->get(),
            'template' => $template,
            'student' => $student,
            'staff' => $staff,
            'replyTo' => $replyTo,
            'studentQuery' => (string) $request->query('student'),
        ]);
    }

    public function store(LetterRequest $request): RedirectResponse
    {
        $letter = $this->letters->createOutgoing($request->validated(), $request->user());

        return redirect()->route('letters.outgoing.show', $letter)->with('success', __('letters.draft_saved'));
    }

    public function edit(Letter $letter): View
    {
        abort_unless($letter->isOutgoing() && $letter->isDraft(), 404);

        return view('letters.edit', ['letter' => $letter, 'staffList' => Staff::query()->orderBy('first_name')->get()]);
    }

    public function update(LetterRequest $request, Letter $letter): RedirectResponse
    {
        abort_unless($letter->isOutgoing(), 404);
        $data = $request->validated();
        unset($data['letter_template_id']);
        $this->letters->update($letter, $data, $request->user());

        return redirect()->route('letters.outgoing.show', $letter)->with('success', __('letters.draft_saved'));
    }

    public function issue(Request $request, Letter $letter): RedirectResponse
    {
        $letter = $this->letters->issue($letter, $request->user());

        return redirect()->route('letters.outgoing.show', $letter)->with('success', __('letters.issued', ['number' => $letter->displayNumber()]));
    }

    // ---------- وارده ----------

    public function createIncoming(Request $request): View
    {
        return view('letters.incoming-form', [
            'letter' => new Letter(['letter_date' => now(), 'received_on' => now(), 'type' => LetterType::General]),
            'staffList' => Staff::query()->orderBy('first_name')->get(),
        ]);
    }

    public function storeIncoming(IncomingLetterRequest $request): RedirectResponse
    {
        $letter = $this->letters->registerIncoming($request->letterData(), $request->user(), $request->file('scan'));

        return redirect()->route('letters.incoming.show', $letter)->with('success', __('letters.registered', ['number' => $letter->displayNumber()]));
    }

    public function editIncoming(Letter $letter): View
    {
        abort_if($letter->isOutgoing() || $letter->isCancelled(), 404);

        return view('letters.incoming-form', ['letter' => $letter, 'staffList' => Staff::query()->orderBy('first_name')->get()]);
    }

    public function updateIncoming(IncomingLetterRequest $request, Letter $letter): RedirectResponse
    {
        abort_if($letter->isOutgoing(), 404);
        $this->letters->update($letter, $request->letterData(), $request->user());

        return redirect()->route('letters.incoming.show', $letter)->with('success', __('letters.saved'));
    }

    // ---------- مشترک ----------

    public function show(Request $request, Letter $letter): View
    {
        $expected = $request->routeIs('letters.incoming.*') ? LetterDirection::Incoming : LetterDirection::Outgoing;
        abort_unless($letter->direction === $expected, 404);
        $letter->load(['attachments', 'student', 'staff', 'replyTo', 'replies', 'creator', 'issuer', 'canceller']);

        if ($request->boolean('print')) {
            abort_unless($letter->isOutgoing(), 404);

            return view('letters.print', ['letter' => $letter]);
        }

        return view('letters.show', ['letter' => $letter]);
    }

    public function cancel(Request $request, Letter $letter): RedirectResponse
    {
        $data = $request->validate(['reason' => ['required', 'string', 'max:500']], [], ['reason' => __('letters.cancel_reason')]);
        $this->letters->cancel($letter, $data['reason'], $request->user());

        return back()->with('success', __('letters.cancelled'));
    }

    public function storeAttachment(Request $request, Letter $letter): RedirectResponse
    {
        $data = $request->validate([
            'title' => ['required', 'string', 'max:150'],
            'file' => ['required', 'file', 'mimes:pdf,jpg,jpeg,png,webp', 'max:10240'],
        ], [], ['title' => __('letters.attachment_title'), 'file' => __('letters.attachment_file')]);
        $this->letters->addAttachment($letter, $request->file('file'), $data['title'], $request->user());

        return back()->with('success', __('letters.attachment_added'));
    }

    public function attachment(Letter $letter, LetterAttachment $attachment): StreamedResponse
    {
        abort_unless($attachment->letter_id === $letter->id && PrivateFiles::exists($attachment->file_path), 404);

        return PrivateFiles::download($attachment->file_path, $attachment->original_name);
    }

    public function destroyAttachment(Request $request, Letter $letter, LetterAttachment $attachment): RedirectResponse
    {
        abort_unless($attachment->letter_id === $letter->id, 404);
        $this->letters->removeAttachment($letter, $attachment, $request->user());

        return back()->with('success', __('letters.attachment_removed'));
    }

    // ---------- کمک ----------

    private function index(Request $request, LetterDirection $direction): View
    {
        $filters = [
            'q' => (string) $request->query('q', ''),
            'type' => (string) $request->query('type', ''),
            'status' => (string) $request->query('status', ''),
            'from' => (string) $request->query('from', ''),
            'to' => (string) $request->query('to', ''),
            'pending' => $request->boolean('pending'),
        ];

        return view('letters.index', [
            'direction' => $direction,
            'letters' => $this->query($request, $direction)->with(['student', 'staff'])->paginate(Paging::size())->withQueryString(),
            'filters' => $filters,
            'pendingCount' => $direction === LetterDirection::Incoming ? $this->pendingQuery()->count() : 0,
        ]);
    }

    private function query(Request $request, LetterDirection $direction)
    {
        $from = JalaliDate::parse($request->query('from'));
        $to = JalaliDate::parse($request->query('to'));

        return Letter::query()
            ->where('direction', $direction)
            ->search($request->query('q'))
            ->when(LetterType::tryFrom((string) $request->query('type')), fn ($q, $type) => $q->where('type', $type))
            ->when(LetterStatus::tryFrom((string) $request->query('status')), fn ($q, $status) => $q->where('status', $status))
            ->when($from, fn ($q) => $q->where('letter_date', '>=', $from->toDateString()))
            ->when($to, fn ($q) => $q->where('letter_date', '<=', $to->toDateString()))
            ->when($direction === LetterDirection::Incoming && $request->boolean('pending'), fn ($q) => $q->whereIn('id', $this->pendingQuery()->select('id')))
            ->orderByDesc('letter_date')->orderByDesc('id');
    }

    /** وارده‌هایی که جواب می‌خواهند و جواب صادرشده ندارند */
    private function pendingQuery()
    {
        return Letter::query()
            ->where('direction', LetterDirection::Incoming)
            ->where('needs_response', true)
            ->where('status', '!=', LetterStatus::Cancelled)
            ->whereDoesntHave('replies', fn ($q) => $q->where('status', LetterStatus::Issued));
    }

    private function findStudent(?string $value): ?Student
    {
        $value = trim(Digits::toLatin((string) $value));
        if ($value === '') {
            return null;
        }

        return Student::query()->where('admission_no', $value)->first();
    }
}
