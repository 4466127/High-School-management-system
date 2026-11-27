<?php

namespace App\Http\Controllers\Library;

use App\Enums\BookCategory;
use App\Enums\BookLanguage;
use App\Enums\CopyStatus;
use App\Http\Controllers\Controller;
use App\Http\Requests\Library\BookRequest;
use App\Models\Book;
use App\Models\BookCopy;
use App\Models\BookLoan;
use App\Models\GradeLevel;
use App\Models\Subject;
use App\Services\LibraryService;
use App\Support\Csv;
use App\Support\Digits;
use App\Support\Paging;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;
use Symfony\Component\HttpFoundation\StreamedResponse;

/**
 * فهرست کتاب‌ها (مرحله‌ی ۱۷). دیدن و جست‌وجو: library.view؛ ثبت، ویرایش و جلدها: library.manage (در مسیرها).
 * جست‌وجو: عنوان، نویسنده، مترجم، ناشر، ISBN، کلیدواژه یا نمبر ثبت دقیق یک جلد.
 */
class CatalogController extends Controller
{
    public function __construct(private readonly LibraryService $library) {}

    public function index(Request $request): View
    {
        $books = $this->filtered($request)->withCopyCounts()->with(['gradeLevel', 'subject'])
            ->orderBy('title')->paginate(Paging::size())->withQueryString();

        return view('library.catalog.index', [
            'books' => $books,
            'filters' => $request->only(['q', 'category', 'language', 'grade', 'available']),
            'grades' => GradeLevel::orderBy('sort_order')->pluck('name', 'id'),
            'totals' => [
                'titles' => Book::count(),
                'copies' => BookCopy::where('status', '!=', CopyStatus::Withdrawn->value)->count(),
                'available' => BookCopy::where('status', CopyStatus::Available->value)->count(),
                'on_loan' => BookLoan::active()->count(),
            ],
            'canManage' => $request->user()->hasPermission('library.manage'),
        ]);
    }

    public function export(Request $request): StreamedResponse
    {
        $cols = __('library.export_columns');
        $rows = $this->filtered($request)->withCopyCounts()->with(['gradeLevel', 'subject', 'copies'])->orderBy('title')->get()
            ->map(fn (Book $b) => [
                $b->title, $b->author, $b->translator, $b->publisher, $b->publish_year, $b->edition, $b->isbn, $b->category->label(),
                $b->language->label(), $b->gradeLevel?->name, $b->subject?->name, $b->call_number,
                $b->copies_total, $b->copies_available, $b->copies_on_loan,
                $b->copies->where('status', '!=', CopyStatus::Withdrawn)->pluck('accession_no')->implode(' '),
            ]);

        return Csv::download('library-catalog.csv', array_values($cols), $rows);
    }

    public function create(): View
    {
        return view('library.catalog.form', $this->formData(new Book(['category' => 'general', 'language' => 'dari'])) + [
            'nextAccession' => $this->library->nextAccession(),
        ]);
    }

    public function store(BookRequest $request): RedirectResponse
    {
        $v = $request->validated();
        $book = $this->library->createBook($request->bookData(), (int) $v['copies'], $v['start_accession'] ?? null, $request->copyData(), $request->user());

        return redirect()->route('library.catalog.show', $book)->with('success', __('library.book_created', ['count' => Digits::toPersian((string) $v['copies'])]));
    }

    public function show(Request $request, Book $book): View
    {
        $book->load(['gradeLevel', 'subject', 'copies.activeLoan.student', 'copies.activeLoan.staff']);
        $canManage = $request->user()->hasPermission('library.manage');

        return view('library.catalog.show', [
            'book' => $book,
            'canManage' => $canManage,
            'nextAccession' => $canManage ? $this->library->nextAccession() : null,
            'history' => $canManage ? BookLoan::with(['copy', 'student', 'staff'])->whereHas('copy', fn ($q) => $q->where('book_id', $book->id))
                ->latest('loaned_on')->latest('id')->limit(30)->get() : collect(),
            'timesBorrowed' => BookLoan::whereHas('copy', fn ($q) => $q->where('book_id', $book->id))->count(),
        ]);
    }

    public function edit(Book $book): View
    {
        return view('library.catalog.form', $this->formData($book));
    }

    public function update(BookRequest $request, Book $book): RedirectResponse
    {
        $this->library->updateBook($book, $request->bookData(), $request->user());

        return redirect()->route('library.catalog.show', $book)->with('success', __('library.saved'));
    }

    public function destroy(Request $request, Book $book): RedirectResponse
    {
        $this->library->deleteBook($book, $request->user());

        return redirect()->route('library.catalog.index')->with('success', __('library.book_deleted'));
    }

    /** برچسب پشت جلدها برای چاپ: همه‌ی جلدهای فعال یا جلدهای انتخاب‌شده (?copies[]=) */
    public function labels(Request $request, Book $book): View
    {
        $ids = array_filter(array_map('intval', (array) $request->query('copies', [])));
        $copies = $book->copies()->where('status', '!=', CopyStatus::Withdrawn->value)->when($ids, fn ($q) => $q->whereIn('id', $ids))->get();

        return view('library.catalog.labels', ['book' => $book, 'copies' => $copies]);
    }

    private function filtered(Request $request): Builder
    {
        return Book::query()
            ->search($request->query('q'))
            ->when(BookCategory::tryFrom((string) $request->query('category')), fn ($q, $c) => $q->where('category', $c->value))
            ->when(BookLanguage::tryFrom((string) $request->query('language')), fn ($q, $l) => $q->where('language', $l->value))
            ->when((int) $request->query('grade'), fn ($q, $g) => $q->where('grade_level_id', $g))
            ->when($request->query('available') === '1', fn ($q) => $q->whereHas('copies', fn ($c) => $c->where('status', CopyStatus::Available->value)));
    }

    /** @return array<string, mixed> */
    private function formData(Book $book): array
    {
        return [
            'book' => $book,
            'grades' => GradeLevel::orderBy('sort_order')->pluck('name', 'id')->all(),
            'subjects' => Subject::orderBy('name')->pluck('name', 'id')->all(),
        ];
    }
}
