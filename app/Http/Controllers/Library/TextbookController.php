<?php

namespace App\Http\Controllers\Library;

use App\Enums\BookCategory;
use App\Enums\CopyStatus;
use App\Enums\EnrollmentStatus;
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
 * توزیع و جمع‌آوری کتاب‌های درسی (مرحله‌ی ۱۷؛ can:library.manage): یک کتاب به همه‌ی شاگردان یک بخش تا آخر سال،
 * و بازگشت گروهی در آخر سال. کتاب درسی در حد امانت عادی حساب نمی‌شود.
 */
class TextbookController extends Controller
{
    public function __construct(private readonly LibraryService $library) {}

    public function index(Request $request): View
    {
        $year = AcademicYear::current();
        $sections = $year ? Section::ordered()->where('academic_year_id', $year->id)->get() : collect();
        $section = $sections->firstWhere('id', (int) $request->query('section'));
        $books = Book::query()->withCopyCounts()->where('category', BookCategory::Textbook->value)
            ->when($section, fn ($q) => $q->orderByRaw('grade_level_id = ? desc', [$section->grade_level_id]))
            ->orderBy('title')->get();
        $book = $books->firstWhere('id', (int) $request->query('book'));

        $rows = collect();
        if ($section && $book) {
            $loans = BookLoan::active()->with('copy')->whereHas('copy', fn ($q) => $q->where('book_id', $book->id))->get()->keyBy('student_id');
            $rows = Enrollment::with('student')->where('section_id', $section->id)->where('status', EnrollmentStatus::Active)
                ->orderBy('roll_no')->orderBy('id')->get()
                ->map(fn ($e) => ['enrollment' => $e, 'loan' => $loans->get($e->student_id)]);
        }

        return view('library.textbooks', [
            'year' => $year,
            'sections' => $sections,
            'section' => $section,
            'books' => $books,
            'book' => $book,
            'rows' => $rows,
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
        $n = fn ($v) => Digits::toPersian((string) $v);

        return back()->with($result['missing'] > 0 ? 'warning' : 'success', __('library.distributed', ['issued' => $n($result['issued']), 'already' => $n($result['already'])])
            .($result['missing'] > 0 ? ' '.__('library.distributed_missing', ['count' => $n($result['missing'])]) : ''));
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
}
