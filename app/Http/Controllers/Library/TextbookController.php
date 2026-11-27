<?php

namespace App\Http\Controllers\Library;

use App\Enums\BookCategory;
use App\Enums\CopyStatus;
use App\Enums\EnrollmentStatus;
use App\Enums\LoanKind;
use App\Http\Controllers\Controller;
use App\Models\AcademicYear;
use App\Models\Book;
use App\Models\BookCopy;
use App\Models\BookLoan;
use App\Models\Enrollment;
use App\Models\Section;
use App\Rules\ValidJalaliDate;
use App\Services\LibraryService;
use App\Support\Digits;
use App\Support\JalaliDate;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Illuminate\View\View;

/**
 * توزیع و جمع‌آوری کتاب‌های درسی (مرحله‌ی ۱۷؛ can:library.manage). قاعده‌ی مکاتب: کتاب درسی اول سال به شاگرد داده
 * و آخر سال پس گرفته می‌شود. فقط کتاب‌های درسی صنف همان بخش دیده می‌شود (کتاب درسی بی صنف جدا).
 * بخش بدون کتاب ← خلاصه‌ی همه‌ی کتاب‌های صنف با «توزیع همه» و «جمع‌آوری همه»؛ بخش + کتاب ← فهرست شاگردان.
 */
class TextbookController extends Controller
{
    public function __construct(private readonly LibraryService $library) {}

    public function index(Request $request): View
    {
        $year = AcademicYear::current();
        $sections = $year ? Section::ordered()->where('academic_year_id', $year->id)->get() : collect();
        $section = $sections->firstWhere('id', (int) $request->query('section'));

        $books = $section ? LibraryService::gradeTextbooks($section) : collect();
        // کتاب درسی که صنفش در فهرست کتاب‌ها تعیین نشده (تا کتابدار آن را درست کند)
        $ungraded = $section ? Book::query()->withCopyCounts()->where('category', BookCategory::Textbook->value)->whereNull('grade_level_id')->orderBy('title')->get() : collect();
        $book = $books->concat($ungraded)->firstWhere('id', (int) $request->query('book'));

        $enrollments = $section ? Enrollment::with('student')->where('section_id', $section->id)->where('status', EnrollmentStatus::Active)
            ->orderBy('roll_no')->orderBy('id')->get() : collect();
        $rows = collect();
        $haveByBook = collect();
        if ($section && $book) {
            $loans = BookLoan::active()->with('copy')->whereHas('copy', fn ($q) => $q->where('book_id', $book->id))->get()->keyBy('student_id');
            $rows = $enrollments->map(fn ($e) => ['enrollment' => $e, 'loan' => $loans->get($e->student_id)]);
        } elseif ($section) {
            // شمار شاگردانی که هر کتاب صنف را دارند
            $haveByBook = BookLoan::active()->where('kind', LoanKind::Textbook->value)->whereIn('student_id', $enrollments->pluck('student_id'))
                ->with('copy:id,book_id')->get()->groupBy(fn ($l) => $l->copy->book_id)->map->count();
        }

        return view('library.textbooks', [
            'year' => $year,
            'sections' => $sections,
            'section' => $section,
            'books' => $books,
            'ungraded' => $ungraded,
            'book' => $book,
            'rows' => $rows,
            'studentCount' => $enrollments->count(),
            'haveByBook' => $haveByBook,
            'available' => $book ? BookCopy::where('book_id', $book->id)->where('status', CopyStatus::Available->value)->count() : 0,
            'defaultDue' => $year ? JalaliDate::format($year->end_date) : '',
            'conditions' => LoanController::returnConditions(),
        ]);
    }

    public function distribute(Request $request): RedirectResponse
    {
        $data = $request->validate([
            'section' => ['required', 'integer', 'exists:sections,id'],
            'book' => ['required', 'integer', 'exists:books,id'],
            'due_on' => ['required', new ValidJalaliDate],
        ], [], __('library.attributes'));
        $result = $this->library->distributeTextbooks(Section::findOrFail($data['section']), Book::findOrFail($data['book']), JalaliDate::parse($data['due_on'])->toDateString(), $request->user());

        return back()->with($result['missing'] > 0 ? 'warning' : 'success', $this->distributedMessage($result));
    }

    /** اول سال: همه‌ی کتاب‌های درسی صنف به همه‌ی شاگردان بخش */
    public function distributeAll(Request $request): RedirectResponse
    {
        $data = $request->validate([
            'section' => ['required', 'integer', 'exists:sections,id'],
            'due_on' => ['required', new ValidJalaliDate],
        ], [], __('library.attributes'));
        $result = $this->library->distributeAllTextbooks(Section::with('gradeLevel')->findOrFail($data['section']), JalaliDate::parse($data['due_on'])->toDateString(), $request->user());

        return back()->with($result['missing'] > 0 ? 'warning' : 'success',
            __('library.distributed_all', ['books' => Digits::toPersian((string) $result['books'])]).' '.$this->distributedMessage($result));
    }

    public function collect(Request $request): RedirectResponse
    {
        $data = $request->validate([
            'loans' => ['required', 'array', 'max:500'],
            'loans.*' => ['integer'],
            'condition' => ['nullable', Rule::in(array_keys(LoanController::returnConditions()))],
        ], ['loans.required' => __('library.select_loans')], __('library.attributes'));
        $count = $this->library->collect(array_map('intval', $data['loans']), $data['condition'] ?? null, $request->user());

        return back()->with('success', __('library.collected', ['count' => Digits::toPersian((string) $count)]));
    }

    /** آخر سال: همه‌ی کتاب‌های درسی شاگردان بخش */
    public function collectAll(Request $request): RedirectResponse
    {
        $data = $request->validate([
            'section' => ['required', 'integer', 'exists:sections,id'],
            'condition' => ['nullable', Rule::in(array_keys(LoanController::returnConditions()))],
        ], [], __('library.attributes'));
        $count = $this->library->collectSectionTextbooks(Section::findOrFail($data['section']), $data['condition'] ?? null, $request->user());

        return back()->with($count > 0 ? 'success' : 'warning', $count > 0 ? __('library.collected', ['count' => Digits::toPersian((string) $count)]) : __('library.nothing_to_collect'));
    }

    /** @param  array{issued: int, already: int, missing: int}  $result */
    private function distributedMessage(array $result): string
    {
        $n = fn ($v) => Digits::toPersian((string) $v);

        return __('library.distributed', ['issued' => $n($result['issued']), 'already' => $n($result['already'])])
            .($result['missing'] > 0 ? ' '.__('library.distributed_missing', ['count' => $n($result['missing'])]) : '');
    }
}
