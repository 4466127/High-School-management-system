<?php

namespace App\Http\Controllers\Library;

use App\Enums\BorrowerType;
use App\Enums\CopyCondition;
use App\Enums\CopyStatus;
use App\Enums\EmploymentStatus;
use App\Enums\EnrollmentStatus;
use App\Enums\LoanKind;
use App\Enums\LoanStatus;
use App\Enums\StudentStatus;
use App\Http\Controllers\Controller;
use App\Models\AcademicYear;
use App\Models\Book;
use App\Models\BookCopy;
use App\Models\BookLoan;
use App\Models\Enrollment;
use App\Models\Section;
use App\Models\Staff;
use App\Models\Student;
use App\Rules\ValidJalaliDate;
use App\Services\AttendanceService;
use App\Services\LibraryService;
use App\Support\Csv;
use App\Support\Digits;
use App\Support\JalaliDate;
use App\Support\Paging;
use App\Support\PersianText;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Collection;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;
use Illuminate\View\View;
use Symfony\Component\HttpFoundation\StreamedResponse;

/**
 * میز امانت (مرحله‌ی ۱۷؛ can:library.manage): پیدا کردن امانت‌گیرنده، امانت با نمبر ثبت، بازگشت سریع،
 * تمدید، گم شدن؛ فهرست امانت‌ها با فیلتر دیرکرد، چاپ (?print=1) و CSV.
 */
class LoanController extends Controller
{
    public function __construct(private readonly LibraryService $library) {}

    public function index(Request $request): View
    {
        $year = AcademicYear::current();
        $loans = $this->filtered($request)->with(['copy.book', 'student.currentEnrollment.section.gradeLevel', 'staff'])
            ->orderBy('due_on')->orderBy('id');
        $print = $request->boolean('print');

        return view('library.loans.index', [
            'loans' => $print ? $loans->get() : $loans->paginate(Paging::size())->withQueryString(),
            'filters' => $request->only(['status', 'type', 'kind', 'section', 'q']),
            'sections' => $year ? Section::ordered()->where('academic_year_id', $year->id)->get() : collect(),
            'matches' => $this->matches((string) $request->query('find')),
            'find' => (string) $request->query('find'),
            'counts' => [
                'active' => BookLoan::active()->count(),
                'overdue' => BookLoan::overdue()->count(),
                'due_today' => BookLoan::active()->where('due_on', AttendanceService::today()->toDateString())->count(),
                'returned_today' => BookLoan::where('status', LoanStatus::Returned->value)->where('returned_on', AttendanceService::today()->toDateString())->count(),
            ],
            'conditions' => self::returnConditions(),
            'print' => $print,
        ]);
    }

    public function export(Request $request): StreamedResponse
    {
        $rows = $this->filtered($request)->with(['copy.book', 'student.currentEnrollment.section.gradeLevel', 'staff'])->orderBy('due_on')->get()
            ->map(fn (BookLoan $l) => [
                $l->copy->accession_no, $l->copy->book->title, $l->borrowerName(), $l->borrower_type->label(), self::borrowerDetail($l),
                $l->kind->label(), JalaliDate::format($l->loaned_on), JalaliDate::format($l->due_on), $l->returned_on ? JalaliDate::format($l->returned_on) : '',
                $l->status->label(), $l->overdueDays() ?: '',
            ]);

        return Csv::download('library-loans.csv', array_values(__('library.loan_export_columns')), $rows);
    }

    /** نمبر اساس دقیق ← مستقیم صفحه‌ی امانت‌گیرنده؛ در غیر آن فهرست نام‌های مشابه */
    public function lookup(Request $request): RedirectResponse
    {
        $find = trim(Digits::toLatin((string) $request->query('find')));
        if ($find === '') {
            return redirect()->route('library.loans.index');
        }
        $student = Student::where('admission_no', $find)->first();
        if ($student) {
            return redirect()->route('library.borrowers.student', $student);
        }

        return redirect()->route('library.loans.index', ['find' => $find]);
    }

    public function student(Request $request, Student $student): View
    {
        return $this->borrowerPage($request, $student, BorrowerType::Student);
    }

    public function staff(Request $request, Staff $staff): View
    {
        return $this->borrowerPage($request, $staff, BorrowerType::Staff);
    }

    public function storeStudent(Request $request, Student $student): RedirectResponse
    {
        return $this->issue($request, $student);
    }

    public function storeStaff(Request $request, Staff $staff): RedirectResponse
    {
        return $this->issue($request, $staff);
    }

    public function returnLoan(Request $request, BookLoan $loan): RedirectResponse
    {
        $data = $request->validate([
            'condition' => ['nullable', Rule::in(array_keys(self::returnConditions()))],
            'note' => ['nullable', 'string', 'max:300'],
        ], [], __('library.attributes'));
        $this->library->returnLoan($loan, $data['condition'] ?? null, $data['note'] ?? null, $request->user());

        return back()->with('success', $this->returnedMessage($loan->fresh(['copy.book'])));
    }

    /** بازگشت با نمبر ثبت (بی پیدا کردن امانت‌گیرنده) */
    public function quickReturn(Request $request): RedirectResponse
    {
        $data = $request->validate([
            'return_accession' => ['required', 'string', 'max:30'],
            'condition' => ['nullable', Rule::in(array_keys(self::returnConditions()))],
        ], [], __('library.attributes'));
        $copy = $this->library->findCopy($data['return_accession']);
        $loan = $copy?->activeLoan;
        if (! $loan) {
            throw ValidationException::withMessages(['return_accession' => $copy ? __('library.copy_not_on_loan', ['no' => Digits::toPersian($copy->accession_no)]) : __('library.copy_not_found')]);
        }
        $this->library->returnLoan($loan, $data['condition'] ?? null, null, $request->user());

        return back()->with('success', $this->returnedMessage($loan->fresh(['copy.book', 'student', 'staff'])));
    }

    public function renew(Request $request, BookLoan $loan): RedirectResponse
    {
        $this->library->renew($loan, $request->user());

        return back()->with('success', __('library.renewed', ['date' => JalaliDate::format($loan->fresh()->due_on)]));
    }

    public function lost(Request $request, BookLoan $loan): RedirectResponse
    {
        $data = $request->validate(['note' => ['required', 'string', 'max:300']], [], __('library.attributes'));
        $this->library->markLost($loan, $data['note'], $request->user());

        return back()->with('success', __('library.marked_lost'));
    }

    // ------------------------------------------------------------------

    private function issue(Request $request, Student|Staff $borrower): RedirectResponse
    {
        // با نمبر ثبت (جلد روی میز) یا با انتخاب کتاب از جستجو (اولین جلد موجود)
        $data = $request->validate([
            'accession_no' => ['required_without:book_id', 'nullable', 'string', 'max:30'],
            'book_id' => ['nullable', 'integer', 'exists:books,id'],
            'due_on' => ['nullable', new ValidJalaliDate],
            'notes' => ['nullable', 'string', 'max:300'],
        ], ['accession_no.required_without' => __('library.accession_or_book')], __('library.attributes'));
        if (! empty($data['accession_no'])) {
            $copy = $this->library->findCopy($data['accession_no']);
            if (! $copy) {
                throw ValidationException::withMessages(['accession_no' => __('library.copy_not_found')]);
            }
        } else {
            $copy = BookCopy::with('book')->where('book_id', $data['book_id'])->where('status', CopyStatus::Available->value)->orderBy('id')->first();
            if (! $copy) {
                throw ValidationException::withMessages(['accession_no' => __('library.no_copy_available')]);
            }
        }
        $loan = $this->library->issue($copy, $borrower, JalaliDate::parse($data['due_on'] ?? null)?->toDateString(), $request->user(), $data['notes'] ?? null);

        return back()->with('success', __('library.issued', ['title' => $copy->book->title, 'date' => JalaliDate::format($loan->due_on)]));
    }

    private function borrowerPage(Request $request, Student|Staff $borrower, BorrowerType $type): View
    {
        $bookQ = trim((string) $request->query('book_q'));
        $isStudent = $type === BorrowerType::Student;
        $history = BookLoan::with('copy.book')->where($isStudent ? 'student_id' : 'staff_id', $borrower->id)
            ->where('status', '!=', LoanStatus::Active->value)->latest('returned_on')->latest('id')->limit(30)->get();
        $rules = LibraryService::rules();
        $active = $this->library->activeLoansOf($borrower);

        return view('library.loans.borrower', [
            'borrower' => $borrower,
            'type' => $type,
            'active' => $active,
            'history' => $history,
            'section' => $isStudent ? $borrower->currentEnrollment?->section : null,
            'canBorrow' => $isStudent ? $borrower->status === StudentStatus::Active && ! $borrower->trashed() : $borrower->employment_status !== EmploymentStatus::Left && ! $borrower->trashed(),
            'limit' => $isStudent ? $rules['max_student'] : $rules['max_staff'],
            'loanCount' => $active->where('kind', LoanKind::Loan)->count(),
            'defaultDue' => LibraryService::defaultDue($type),
            'storeRoute' => $isStudent ? route('library.borrowers.student.store', $borrower) : route('library.borrowers.staff.store', $borrower),
            'conditions' => self::returnConditions(),
            'rules' => $rules,
            'bookQ' => $bookQ,
            'bookResults' => $bookQ === '' ? null : Book::query()->withCopyCounts()->search($bookQ)
                ->orderByRaw('(select count(*) from book_copies where book_copies.book_id = books.id and book_copies.status = ?) = 0', [CopyStatus::Available->value])
                ->orderBy('title')->limit(15)->get(),
        ]);
    }

    private function filtered(Request $request): Builder
    {
        $status = (string) $request->query('status', 'active');
        $today = AttendanceService::today();
        $q = BookLoan::query();
        match ($status) {
            'overdue' => $q->overdue(),
            'due_soon' => $q->active()->whereBetween('due_on', [$today->toDateString(), $today->addDays(3)->toDateString()]),
            'due_today' => $q->active()->where('due_on', $today->toDateString()),
            'returned_today' => $q->where('status', LoanStatus::Returned->value)->where('returned_on', $today->toDateString()),
            'returned' => $q->where('status', LoanStatus::Returned->value),
            'lost' => $q->where('status', LoanStatus::Lost->value),
            'all' => $q,
            default => $q->active(),
        };
        if ($type = BorrowerType::tryFrom((string) $request->query('type'))) {
            $q->where('borrower_type', $type->value);
        }
        if ($kind = LoanKind::tryFrom((string) $request->query('kind'))) {
            $q->where('kind', $kind->value);
        }
        if ($section = (int) $request->query('section')) {
            $q->whereIn('student_id', Enrollment::where('section_id', $section)->where('status', EnrollmentStatus::Active)->select('student_id'));
        }
        // هر واژه: نمبر ثبت، نام کتاب یا نویسنده، نام یا نمبر اساس امانت‌گیرنده
        foreach (PersianText::searchTerms($request->query('q')) as $word) {
            $q->where(fn (Builder $w) => $w
                ->whereHas('copy', fn ($c) => $c->where('accession_no', $word)->orWhereHas('book', fn ($b) => PersianText::whereMatches($b, ['title', 'author'], $word)))
                ->orWhereHas('student', fn ($s) => PersianText::whereMatches($s, ['first_name', 'last_name', 'father_name'], $word, exact: ['admission_no']))
                ->orWhereHas('staff', fn ($s) => PersianText::whereMatches($s, ['first_name', 'last_name', 'father_name'], $word)));
        }

        return $q;
    }

    /** @return array{students: Collection, staff: Collection}|null */
    private function matches(string $find): ?array
    {
        if (trim($find) === '') {
            return null;
        }

        return [
            'students' => Student::query()->search($find)->with('currentEnrollment.section.gradeLevel')->orderBy('first_name')->limit(30)->get(),
            'staff' => Staff::query()->search($find)->orderBy('first_name')->limit(15)->get(),
        ];
    }

    private function returnedMessage(BookLoan $loan): string
    {
        $days = $loan->overdueDays();

        return __('library.returned', ['title' => $loan->copy->book->title, 'name' => $loan->borrowerName()])
            .($days > 0 ? ' '.__('library.returned_late', ['days' => Digits::toPersian((string) $days)]) : '');
    }

    /** @return array<string, string> حالت جلد هنگام بازگشت (+ «خراب شده») */
    public static function returnConditions(): array
    {
        return CopyCondition::options() + ['damaged' => __('library.condition_damaged')];
    }

    /** صنف و بخش دانش‌آموز یا وظیفه‌ی کارمند */
    public static function borrowerDetail(BookLoan $loan): string
    {
        if ($loan->borrower_type === BorrowerType::Student) {
            $section = $loan->student?->currentEnrollment?->section;

            return trim(($loan->student?->admission_no ? __('students.admission_no').' '.$loan->student->admission_no : '').($section ? ' · '.$section->gradeLevel->name.' '.$section->name : ''), ' ·');
        }

        return (string) ($loan->staff?->position ?: $loan->staff?->staff_type?->label());
    }
}
