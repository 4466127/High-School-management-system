<?php

namespace App\Http\Controllers\Library;

use App\Enums\BookCategory;
use App\Enums\BorrowerType;
use App\Enums\CopyStatus;
use App\Enums\EnrollmentStatus;
use App\Enums\LoanKind;
use App\Http\Controllers\Controller;
use App\Models\AcademicYear;
use App\Models\Book;
use App\Models\BookCopy;
use App\Models\BookLoan;
use App\Models\Enrollment;
use App\Models\Setting;
use App\Services\ActivityLogger;
use App\Services\LibraryService;
use App\Support\Money;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\View\View;

/**
 * گزارش کتابخانه و تنظیمات امانت (مرحله‌ی ۱۷؛ can:library.manage). فقط خواندن، به‌جز ذخیره‌ی قاعده‌ها.
 * گزارش: موجودی هر دسته و وضعیت جلدها، ارزش موجودی، پرامانت‌ترین کتاب‌ها و امانت هر صنف در سال جاری، جلدهای گم و خراب.
 */
class ReportController extends Controller
{
    public function index(Request $request): View
    {
        $year = AcademicYear::current();
        $from = $year ? $year->start_date->toDateString() : now()->subYear()->toDateString();

        $byCategory = Book::query()->select('books.category', DB::raw('count(distinct books.id) as titles'))
            ->selectRaw("sum(case when book_copies.status != 'withdrawn' then 1 else 0 end) as copies")
            ->selectRaw("sum(case when book_copies.status = 'available' then 1 else 0 end) as available")
            ->selectRaw("sum(case when book_copies.status = 'on_loan' then 1 else 0 end) as on_loan")
            ->leftJoin('book_copies', 'book_copies.book_id', '=', 'books.id')
            ->groupBy('books.category')->get()->keyBy(fn ($r) => $r->category instanceof BookCategory ? $r->category->value : $r->category);

        $statusCounts = BookCopy::query()->select('status', DB::raw('count(*) as n'))->groupBy('status')->pluck('n', 'status');

        $top = BookLoan::query()->join('book_copies', 'book_copies.id', '=', 'book_loans.book_copy_id')
            ->where('book_loans.kind', LoanKind::Loan->value)->where('book_loans.loaned_on', '>=', $from)
            ->select('book_copies.book_id', DB::raw('count(*) as n'))->groupBy('book_copies.book_id')->orderByDesc('n')->limit(10)->get();
        $topBooks = Book::whereIn('id', $top->pluck('book_id'))->get()->keyBy('id');

        $bySection = collect();
        if ($year) {
            $bySection = Enrollment::query()->with('section.gradeLevel')
                ->where('enrollments.academic_year_id', $year->id)->where('enrollments.status', EnrollmentStatus::Active)
                ->join('book_loans', 'book_loans.student_id', '=', 'enrollments.student_id')
                ->where('book_loans.kind', LoanKind::Loan->value)->where('book_loans.loaned_on', '>=', $from)
                ->select('enrollments.section_id', DB::raw('count(book_loans.id) as loans'), DB::raw('count(distinct book_loans.student_id) as readers'))
                ->groupBy('enrollments.section_id')->get()
                ->sortBy(fn ($r) => [$r->section->gradeLevel->sort_order, $r->section->name])->values();
        }

        return view('library.reports', [
            'year' => $year,
            'byCategory' => $byCategory,
            'statusCounts' => $statusCounts,
            'value' => Money::format((string) (BookCopy::where('status', '!=', CopyStatus::Withdrawn->value)->sum('price') ?: 0)),
            'top' => $top->map(fn ($r) => ['book' => $topBooks->get($r->book_id), 'count' => (int) $r->n])->filter(fn ($r) => $r['book']),
            'bySection' => $bySection,
            'loansThisYear' => BookLoan::where('kind', LoanKind::Loan->value)->where('loaned_on', '>=', $from)->count(),
            'staffLoans' => BookLoan::where('kind', LoanKind::Loan->value)->where('loaned_on', '>=', $from)->where('borrower_type', BorrowerType::Staff->value)->count(),
            'overdue' => BookLoan::overdue()->count(),
            'problems' => BookCopy::with('book')->whereIn('status', [CopyStatus::Lost->value, CopyStatus::Damaged->value])
                ->latest('status_changed_on')->latest('id')->limit(50)->get(),
            'print' => $request->boolean('print'),
        ]);
    }

    public function setup(): View
    {
        return view('library.setup', [
            'rules' => LibraryService::rules(),
            'name' => (string) Setting::get('library_name', ''),
        ]);
    }

    public function saveSetup(Request $request): RedirectResponse
    {
        $data = $request->validate([
            'library_loan_days_student' => ['required', 'integer', 'min:1', 'max:365'],
            'library_loan_days_staff' => ['required', 'integer', 'min:1', 'max:365'],
            'library_max_student' => ['required', 'integer', 'min:1', 'max:20'],
            'library_max_staff' => ['required', 'integer', 'min:1', 'max:50'],
            'library_max_renewals' => ['required', 'integer', 'min:0', 'max:10'],
            'library_name' => ['nullable', 'string', 'max:150'],
        ], [], __('library.setup_fields'));
        $data['library_block_overdue'] = $request->boolean('library_block_overdue') ? '1' : '0';
        $data['library_name'] = trim((string) ($data['library_name'] ?? ''));

        $old = collect(LibraryService::RULE_KEYS)->mapWithKeys(fn ($k) => [$k => (string) Setting::get($k, '')])->all();
        foreach (LibraryService::RULE_KEYS as $key) {
            Setting::put($key, (string) $data[$key]);
        }
        $new = collect(LibraryService::RULE_KEYS)->mapWithKeys(fn ($k) => [$k => (string) $data[$k]])->all();
        $changed = array_diff_assoc($new, $old);
        if ($changed !== []) {
            app(ActivityLogger::class)->log('settings.library', null, __('library.setup_title'), array_intersect_key($old, $changed), $changed);
        }

        return back()->with('success', __('library.saved'));
    }

    /** نام کتابخانه برای سربرگ چاپ‌ها */
    public static function libraryName(): string
    {
        return trim((string) Setting::get('library_name', '')) ?: __('library.default_name', ['school' => (string) Setting::get('school_name', '')]);
    }
}
