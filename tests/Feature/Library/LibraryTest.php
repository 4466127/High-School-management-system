<?php

namespace Tests\Feature\Library;

use App\Enums\CopyStatus;
use App\Enums\LoanKind;
use App\Enums\LoanStatus;
use App\Enums\Role;
use App\Models\ActivityLog;
use App\Models\Book;
use App\Models\BookCopy;
use App\Models\BookLoan;
use App\Models\GradeLevel;
use App\Models\Setting;
use App\Models\Staff;
use App\Models\User;
use App\Services\ImportService;
use Carbon\CarbonImmutable;
use Database\Seeders\DemoLibrarySeeder;
use Database\Seeders\DemoTextbookSeeder;
use Database\Seeders\RolePermissionSeeder;
use Illuminate\Support\Carbon;
use Tests\Feature\Exams\ExamTestCase;

/** مرحله‌ی ۱۷: کتابخانه — فهرست، جلدها و نمبر ثبت، امانت و قاعده‌ها، بازگشت، گم شدن، کتاب درسی، ورود از Excel، گزارش‌ها */
class LibraryTest extends ExamTestCase
{
    private User $librarian;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(RolePermissionSeeder::class);
        $this->librarian = $this->userWithRole(Role::Librarian);
        $this->freeze('2026-11-20');
    }

    protected function tearDown(): void
    {
        Carbon::setTestNow();
        CarbonImmutable::setTestNow();
        parent::tearDown();
    }

    private function freeze(string $date): void
    {
        Carbon::setTestNow($date.' 10:00:00');
        CarbonImmutable::setTestNow($date.' 10:00:00');
    }

    private function addBook(array $overrides = []): Book
    {
        $this->actingAs($this->librarian)->post(route('library.catalog.store'), array_merge([
            'title' => 'گلستان', 'author' => 'سعدی', 'category' => 'literature', 'language' => 'dari',
            'copies' => 3, 'start_accession' => '', 'source' => 'purchase', 'condition' => 'new', 'price' => '۲۵۰',
        ], $overrides))->assertSessionHasNoErrors()->assertRedirect();

        return Book::latest('id')->firstOrFail();
    }

    private function student(int $i = 0)
    {
        return $this->students[$i]->student;
    }

    public function test_catalog_copies_and_accession_numbers(): void
    {
        $book = $this->addBook(['acquired_on' => '1405/08/01']);
        $this->assertSame(['1', '2', '3'], $book->copies()->pluck('accession_no')->all());
        $this->assertSame('250.00', $book->copies()->first()->price);
        $this->assertSame('2026-10-23', $book->copies()->first()->acquired_on->toDateString());

        // نمبر ثبت با پیشوند و صفر اول
        $this->actingAs($this->librarian)->post(route('library.copies.store', $book), ['copies' => '۲', 'start_accession' => 'ب-009', 'source' => 'donation', 'condition' => 'good'])
            ->assertSessionHasNoErrors();
        $this->assertSame(['1', '2', '3', 'ب-009', 'ب-010'], $book->copies()->pluck('accession_no')->all());
        // نمبر گرفته‌شده
        $this->actingAs($this->librarian)->post(route('library.copies.store', $book), ['copies' => 2, 'start_accession' => '2', 'source' => 'purchase', 'condition' => 'good'])
            ->assertSessionHasErrors('start_accession');
        $this->assertSame(5, $book->copies()->count());
        // پیشنهاد بعدی = بزرگ‌ترین عدد + ۱
        $this->actingAs($this->librarian)->get(route('library.catalog.create'))->assertOk()->assertSee('۴');

        // جست‌وجو با نمبر ثبت و نام؛ معلم می‌بیند ولی تغییر نمی‌دهد؛ حسابدار نمی‌بیند
        $this->actingAs($this->teacher)->get(route('library.catalog.index', ['q' => 'ب-010']))->assertOk()->assertSee('گلستان')->assertDontSee(__('library.add_book'));
        $this->actingAs($this->teacher)->get(route('library.catalog.show', $book))->assertOk()->assertDontSee(__('library.add_copies'));
        $this->actingAs($this->teacher)->get(route('library.catalog.create'))->assertForbidden();
        $this->actingAs($this->userWithRole(Role::Accountant))->get(route('library.catalog.index'))->assertForbidden();

        // ویرایش، چاپ برچسب، حذف فقط بی جلد
        $this->actingAs($this->librarian)->put(route('library.catalog.update', $book), ['title' => 'گلستان سعدی', 'category' => 'literature', 'language' => 'dari'])->assertSessionHasNoErrors();
        $this->assertSame('گلستان سعدی', $book->fresh()->title);
        $this->actingAs($this->librarian)->get(route('library.catalog.labels', $book))->assertOk()->assertSee('ب-۰۱۰');
        $this->actingAs($this->librarian)->delete(route('library.catalog.destroy', $book))->assertSessionHasErrors('book');
        $empty = $this->addBook(['title' => 'کتاب خالی', 'copies' => 0]);
        $this->actingAs($this->librarian)->delete(route('library.catalog.destroy', $empty))->assertSessionHasNoErrors();
        $this->assertNull(Book::find($empty->id));
        $this->assertTrue(ActivityLog::where('action', 'library.copies_added')->exists());
    }

    public function test_lending_rules_renewal_and_return(): void
    {
        Setting::put('library_max_student', '1');
        $book = $this->addBook();
        $ref = $this->addBook(['title' => 'قاموس', 'category' => 'reference', 'is_reference' => 1, 'copies' => 1]);
        $student = $this->student();
        $page = route('library.borrowers.student', $student);

        $this->actingAs($this->librarian)->get(route('library.loans.lookup', ['find' => $student->admission_no]))->assertRedirect($page);
        $this->actingAs($this->librarian)->from($page)->post(route('library.borrowers.student.store', $student), ['accession_no' => '۱'])->assertSessionHasNoErrors();
        $loan = BookLoan::firstOrFail();
        $this->assertSame('2026-12-04', $loan->due_on->toDateString());   // ۱۴ روز
        $this->assertSame(CopyStatus::OnLoan, $loan->copy->status);

        // همان جلد دوباره، حد امانت، کتاب مرجع
        $other = $this->student(1);
        $this->actingAs($this->librarian)->post(route('library.borrowers.student.store', $other), ['accession_no' => '1'])->assertSessionHasErrors('accession_no');
        $this->actingAs($this->librarian)->post(route('library.borrowers.student.store', $student), ['accession_no' => '2'])->assertSessionHasErrors('accession_no');
        $this->actingAs($this->librarian)->post(route('library.borrowers.student.store', $other), ['accession_no' => $ref->copies()->value('accession_no')])->assertSessionHasErrors('accession_no');
        $this->actingAs($this->librarian)->post(route('library.borrowers.student.store', $other), ['accession_no' => '999'])->assertSessionHasErrors('accession_no');

        // تمدید یک بار (پیش‌فرض)
        $this->freeze('2026-11-30');
        $this->actingAs($this->librarian)->post(route('library.loans.renew', $loan))->assertSessionHasNoErrors();
        $this->assertSame('2026-12-14', $loan->fresh()->due_on->toDateString());
        $this->actingAs($this->librarian)->post(route('library.loans.renew', $loan))->assertSessionHasErrors('loan');

        // دیرکرد ← کتاب تازه داده نمی‌شود (برای کارمند هم)
        $this->freeze('2026-12-20');
        $this->assertSame(6, $loan->fresh()->overdueDays());
        $this->actingAs($this->librarian)->get(route('library.loans.index', ['status' => 'overdue']))->assertOk()->assertSee($student->full_name);
        Setting::put('library_max_student', '5');
        $this->actingAs($this->librarian)->post(route('library.borrowers.student.store', $student), ['accession_no' => '2'])->assertSessionHasErrors('accession_no');

        // بازگشت سریع با نمبر ثبت، خراب ← جلد از قفسه بیرون
        $this->actingAs($this->librarian)->post(route('library.loans.quick-return'), ['return_accession' => '۱', 'condition' => 'damaged'])->assertSessionHasNoErrors();
        $loan->refresh();
        $this->assertSame(LoanStatus::Returned, $loan->status);
        $this->assertSame('2026-12-20', $loan->returned_on->toDateString());
        $this->assertSame(CopyStatus::Damaged, $loan->copy->status);
        $this->actingAs($this->librarian)->post(route('library.loans.quick-return'), ['return_accession' => '1'])->assertSessionHasErrors('return_accession');
        $this->actingAs($this->librarian)->post(route('library.copies.status', $loan->copy), ['status' => 'available'])->assertSessionHasNoErrors();
        $this->assertSame(CopyStatus::Available, $loan->copy->fresh()->status);

        // کارمند و صفحه‌ی امانت‌گیرنده
        $staff = Staff::findOrFail($this->teacher->staff_id);
        $this->actingAs($this->librarian)->post(route('library.borrowers.staff.store', $staff), ['accession_no' => '3', 'due_on' => '1405/10/15'])->assertSessionHasNoErrors();
        $this->assertSame('2027-01-05', BookLoan::where('staff_id', $staff->id)->value('due_on')->toDateString());
        $this->actingAs($this->librarian)->get(route('library.borrowers.staff', $staff))->assertOk()->assertSee('گلستان');
        $this->actingAs($this->librarian)->get(route('library.borrowers.student', $student))->assertOk()->assertSee(__('library.borrower_history'));
        // داشبورد معلم: «کتاب‌های امانت من»
        $this->actingAs($this->teacher)->get(route('dashboard'))->assertOk()->assertSee(__('dashboard.my_books'));
        $this->assertTrue(ActivityLog::where('action', 'library.returned')->exists());
    }

    public function test_lost_withdrawn_and_inactive_borrower(): void
    {
        $book = $this->addBook(['copies' => 2]);
        $student = $this->student();
        $this->actingAs($this->librarian)->post(route('library.borrowers.student.store', $student), ['accession_no' => '1']);
        $loan = BookLoan::firstOrFail();

        // جلد امانت وضعیت دستی نمی‌گیرد؛ گم شدن یادداشت می‌خواهد
        $this->actingAs($this->librarian)->post(route('library.copies.status', $loan->copy), ['status' => 'withdrawn', 'reason' => 'x'])->assertSessionHasErrors('status');
        $this->actingAs($this->librarian)->post(route('library.loans.lost', $loan), [])->assertSessionHasErrors('note');
        $this->actingAs($this->librarian)->post(route('library.loans.lost', $loan), ['note' => 'گم کرد'])->assertSessionHasNoErrors();
        $this->assertSame(LoanStatus::Lost, $loan->fresh()->status);
        $this->assertSame(CopyStatus::Lost, $loan->copy->fresh()->status);

        // خارج از استفاده دلیل می‌خواهد و در شمار جلدها نیست
        $copy2 = BookCopy::where('accession_no', '2')->firstOrFail();
        $this->actingAs($this->librarian)->post(route('library.copies.status', $copy2), ['status' => 'withdrawn'])->assertSessionHasErrors('reason');
        $this->actingAs($this->librarian)->post(route('library.copies.status', $copy2), ['status' => 'withdrawn', 'reason' => 'کهنه'])->assertSessionHasNoErrors();
        $this->assertSame(1, Book::withCopyCounts()->findOrFail($book->id)->copies_total);

        // دانش‌آموز غیرفعال امانت نمی‌گیرد
        $this->actingAs($this->librarian)->post(route('library.copies.status', $copy2), ['status' => 'available'])->assertSessionHasNoErrors();
        $other = $this->student(1);
        $other->forceFill(['status' => 'graduated'])->save();
        $this->actingAs($this->librarian)->post(route('library.borrowers.student.store', $other), ['accession_no' => '2'])->assertSessionHasErrors('accession_no');
        // پرونده‌ی دانش‌آموز: زبانه‌ی کتابخانه
        $this->actingAs($this->principal)->get(route('students.show', $student))->assertOk()->assertSee(__('library.student_tab'));
    }

    public function test_textbook_distribution_and_collection(): void
    {
        $book = $this->addBook(['title' => 'ریاضی صنف', 'category' => 'textbook', 'grade_level_id' => $this->section->grade_level_id, 'copies' => 2, 'source' => 'ministry']);
        $this->actingAs($this->librarian)->get(route('library.textbooks.index', ['section' => $this->section->id, 'book' => $book->id]))->assertOk()
            ->assertSee(__('library.not_enough_copies', ['count' => '۱']));

        $this->actingAs($this->librarian)->post(route('library.textbooks.distribute'), ['section' => $this->section->id, 'book' => $book->id, 'due_on' => '1405/09/30'])
            ->assertSessionHas('warning');
        $this->assertSame(2, BookLoan::where('kind', LoanKind::Textbook->value)->active()->count());
        // دوباره: کسی دو جلد نمی‌گیرد
        $this->actingAs($this->librarian)->post(route('library.textbooks.distribute'), ['section' => $this->section->id, 'book' => $book->id, 'due_on' => '1405/09/30']);
        $this->assertSame(2, BookLoan::count());

        // کتاب درسی در حد امانت حساب نمی‌شود
        Setting::put('library_max_student', '1');
        $holder = BookLoan::firstOrFail()->student;
        $novel = $this->addBook(['title' => 'رمان', 'copies' => 1]);
        $this->actingAs($this->librarian)->post(route('library.borrowers.student.store', $holder), ['accession_no' => $novel->copies()->value('accession_no')])->assertSessionHasNoErrors();

        $ids = BookLoan::where('kind', 'textbook')->pluck('id')->all();
        $this->actingAs($this->librarian)->post(route('library.textbooks.collect'), ['loans' => $ids])->assertSessionHasNoErrors();
        $this->assertSame(0, BookLoan::where('kind', 'textbook')->active()->count());
        $this->assertSame(2, $book->copies()->where('status', 'available')->count());
        $this->actingAs($this->librarian)->post(route('library.textbooks.collect'), [])->assertSessionHasErrors('loans');
    }

    /** 1.8.1: فقط کتاب‌های درسی صنف همان بخش؛ توزیع همه (اول سال) و جمع‌آوری همه (آخر سال) */
    public function test_textbooks_of_section_grade_only_with_bulk_distribute_and_collect(): void
    {
        $grade7 = $this->section->grade_level_id;
        $grade1 = GradeLevel::where('sort_order', 1)->value('id');
        $math = $this->addBook(['title' => 'ریاضی صنف هفتم', 'category' => 'textbook', 'grade_level_id' => $grade7, 'copies' => 3, 'source' => 'ministry']);
        $this->addBook(['title' => 'دری صنف هفتم', 'category' => 'textbook', 'grade_level_id' => $grade7, 'copies' => 3, 'source' => 'ministry']);
        $this->addBook(['title' => 'قرآن کریم صنف اول', 'category' => 'textbook', 'grade_level_id' => $grade1, 'copies' => 3, 'source' => 'ministry']);

        $page = $this->actingAs($this->librarian)->get(route('library.textbooks.index', ['section' => $this->section->id]))->assertOk();
        $page->assertSee('ریاضی صنف هفتم')->assertSee('دری صنف هفتم')->assertDontSee('قرآن کریم صنف اول');
        // بی بخش: هیچ کتابی فهرست نمی‌شود
        $this->actingAs($this->librarian)->get(route('library.textbooks.index'))->assertOk()->assertDontSee('ریاضی صنف هفتم');

        // توزیع همه: ۲ کتاب × ۳ شاگرد
        $this->actingAs($this->librarian)->post(route('library.textbooks.distribute-all'), ['section' => $this->section->id, 'due_on' => '1405/12/29'])
            ->assertSessionHasNoErrors()->assertSessionHas('success');
        $this->assertSame(6, BookLoan::where('kind', LoanKind::Textbook->value)->active()->count());
        $this->assertSame(3, BookLoan::active()->whereHas('copy', fn ($q) => $q->where('book_id', $math->id))->count());
        // دوباره: کسی دو جلد نمی‌گیرد
        $this->actingAs($this->librarian)->post(route('library.textbooks.distribute-all'), ['section' => $this->section->id, 'due_on' => '1405/12/29']);
        $this->assertSame(6, BookLoan::count());
        $this->actingAs($this->librarian)->get(route('library.textbooks.index', ['section' => $this->section->id]))->assertSee(__('library.of_limit', ['count' => '۳', 'max' => '۳']));

        // بخش دیگر بی کتاب درسی صنف (صنف اول ندارد بخش) ← خطا
        $grade1Section = $this->makeSection($this->year, 2);
        $this->actingAs($this->librarian)->post(route('library.textbooks.distribute-all'), ['section' => $grade1Section->id, 'due_on' => '1405/12/29'])
            ->assertSessionHasErrors('section');

        // جمع‌آوری همه
        $this->actingAs($this->librarian)->post(route('library.textbooks.collect-all'), ['section' => $this->section->id, 'condition' => 'good'])
            ->assertSessionHas('success');
        $this->assertSame(0, BookLoan::active()->count());
        $this->assertSame(9, BookCopy::where('status', CopyStatus::Available->value)->count());
        $this->actingAs($this->librarian)->post(route('library.textbooks.collect-all'), ['section' => $this->section->id])->assertSessionHas('warning');
    }

    /** 2.1.0: «امروز بازگشت شد» جدا از «موعد بازگشتش امروز»؛ هر عدد فهرست خودش را باز می‌کند */
    public function test_loan_counts_returned_today_and_due_today(): void
    {
        $book = $this->addBook(['copies' => 3]);
        $copies = $book->copies()->pluck('accession_no')->all();
        $this->actingAs($this->librarian)->post(route('library.borrowers.student.store', $this->student()), ['accession_no' => $copies[0], 'due_on' => '1405/08/29']);
        $this->actingAs($this->librarian)->post(route('library.borrowers.student.store', $this->student(1)), ['accession_no' => $copies[1]]);
        $this->actingAs($this->librarian)->post(route('library.loans.quick-return'), ['return_accession' => $copies[1]])->assertSessionHasNoErrors();

        $page = $this->actingAs($this->librarian)->get(route('library.loans.index'))->assertOk();
        $this->assertSame(['active' => 1, 'overdue' => 0, 'due_today' => 1, 'returned_today' => 1], $page->viewData('counts'));
        $page->assertSee(__('library.count_returned_today', ['count' => '۱']));
        $this->assertSame(1, $this->actingAs($this->librarian)->get(route('library.loans.index', ['status' => 'returned_today']))->viewData('loans')->total());
        $this->assertSame(1, $this->actingAs($this->librarian)->get(route('library.loans.index', ['status' => 'due_today']))->viewData('loans')->total());
    }

    public function test_demo_textbook_seeder_fills_grades_once(): void
    {
        $this->seed(DemoTextbookSeeder::class);
        $books = Book::where('category', 'textbook')->where('grade_level_id', $this->section->grade_level_id)->get();
        $this->assertSame(2, $books->count());   // ریاضی و دری صنف هفتم
        $this->assertSame(5, $books->first()->copies()->count());   // ۳ شاگرد + ۲
        $this->seed(DemoTextbookSeeder::class);
        $this->assertSame(2, Book::where('category', 'textbook')->where('grade_level_id', $this->section->grade_level_id)->count());
    }

    /** 1.8.1: امانت با جستجوی نام کتاب (بی دانستن نمبر ثبت) */
    public function test_lend_by_searching_book_title(): void
    {
        $book = $this->addBook(['title' => 'کتاب‌های داستان کوتاه', 'copies' => 2]);
        $student = $this->student();

        // جستجو بی نیم‌فاصله و با «ي» عربی هم پیدا می‌کند
        $this->actingAs($this->librarian)->get(route('library.borrowers.student', ['student' => $student, 'book_q' => 'كتابهاي داستان']))
            ->assertOk()->assertSee('کتاب‌های داستان کوتاه')->assertSee(__('library.available_n', ['count' => '۲']));

        $this->actingAs($this->librarian)->post(route('library.borrowers.student.store', $student), ['book_id' => $book->id])->assertSessionHasNoErrors();
        $this->assertSame('1', BookLoan::firstOrFail()->copy->accession_no);   // اولین جلد موجود
        $this->actingAs($this->librarian)->post(route('library.borrowers.student.store', $this->student(1)), ['book_id' => $book->id])->assertSessionHasNoErrors();
        $this->assertSame(2, BookLoan::active()->count());
        // جلد موجود نمانده
        $this->actingAs($this->librarian)->post(route('library.borrowers.student.store', $this->student(2)), ['book_id' => $book->id])->assertSessionHasErrors('accession_no');
        $this->actingAs($this->librarian)->get(route('library.borrowers.student', ['student' => $this->student(2), 'book_q' => 'داستان']))->assertSee(__('library.none_available'));
        // نه کتاب نه نمبر
        $this->actingAs($this->librarian)->post(route('library.borrowers.student.store', $this->student(2)), [])->assertSessionHasErrors('accession_no');
    }

    public function test_import_books_from_csv(): void
    {
        $path = tempnam(sys_get_temp_dir(), 'lib').'.csv';
        file_put_contents($path, "\xEF\xBB\xBF".implode("\n", [
            'نام کتاب *,نویسنده,دسته,شمار جلد,نمبر ثبت اول,منبع,قیمت,مرجع',
            'بوستان,سعدی,ادبیات,2,500,اهدایی,۱۵۰,',
            'قاموس,,مرجع,1,,,,بلی',
            'بی‌نام,,دسته‌ی ناشناس,1,,,,',
            'بوستان,سعدی,ادبیات,1,,,,',
        ]));
        $service = app(ImportService::class);
        $result = $service->preview('books', $path, 'csv');
        $this->assertSame(['ok', 'ok', 'error', 'duplicate'], array_column($result['rows'], 'status'));

        $summary = $service->import('books', $result['rows'], $this->librarian);
        $this->assertSame(2, $summary['imported']);
        $bustan = Book::where('title', 'بوستان')->firstOrFail();
        $this->assertSame(['500', '501'], $bustan->copies()->pluck('accession_no')->all());
        $this->assertSame('donation', $bustan->copies()->first()->source->value);
        $this->assertTrue(Book::where('title', 'قاموس')->value('is_reference'));
        $this->assertSame('502', Book::where('title', 'قاموس')->firstOrFail()->copies()->value('accession_no'));
        @unlink($path);

        $this->actingAs($this->librarian)->get(route('import.index', ['type' => 'books']))->assertOk()->assertSee(__('import.type_books'));
        $this->actingAs($this->librarian)->get(route('import.template', 'books'))->assertOk();
        $this->actingAs($this->librarian)->get(route('import.template', 'students'))->assertForbidden();
    }

    public function test_reports_setup_exports_and_dashboard(): void
    {
        $book = $this->addBook();
        $student = $this->student();
        $this->actingAs($this->librarian)->post(route('library.borrowers.student.store', $student), ['accession_no' => '1']);

        $this->actingAs($this->librarian)->get(route('library.reports.index'))->assertOk()->assertSee(__('enums.book_category.literature'))->assertSee('گلستان');
        $this->actingAs($this->librarian)->get(route('library.reports.index', ['print' => 1]))->assertOk();
        $this->actingAs($this->librarian)->get(route('library.loans.index', ['print' => 1]))->assertOk()->assertSee($student->full_name);
        $this->assertStringContainsString($student->full_name, $this->actingAs($this->librarian)->get(route('library.loans.export'))->streamedContent());
        $this->assertStringContainsString('گلستان', $this->actingAs($this->librarian)->get(route('library.catalog.export'))->streamedContent());
        $this->actingAs($this->librarian)->get(route('library.loans.index', ['find' => 'شاگرد']))->assertOk()->assertSee($student->full_name);

        $this->actingAs($this->librarian)->put(route('library.setup.update'), [
            'library_loan_days_student' => 7, 'library_loan_days_staff' => 21, 'library_max_student' => 3, 'library_max_staff' => 4, 'library_max_renewals' => 0, 'library_name' => 'کتابخانه‌ی ابن‌سینا',
        ])->assertSessionHasNoErrors();
        $this->assertSame('7', Setting::get('library_loan_days_student'));
        $this->assertSame('0', Setting::get('library_block_overdue'));
        $this->actingAs($this->librarian)->get(route('library.catalog.labels', $book))->assertSee('کتابخانه‌ی ابن‌سینا');
        $this->actingAs($this->teacher)->get(route('library.setup.index'))->assertForbidden();

        $this->actingAs($this->librarian)->get(route('dashboard'))->assertOk()->assertSee(__('dashboard.library_title'));
        // هشدار در فرم انتقال
        $this->actingAs($this->principal)->get(route('student-transfers.out.create', $student))->assertOk()->assertSee('گلستان');
    }

    public function test_demo_seeder(): void
    {
        $this->seed(DemoLibrarySeeder::class);
        $this->assertGreaterThan(20, Book::count());
        $this->assertGreaterThan(0, BookLoan::overdue()->count());
        $this->assertGreaterThan(0, BookLoan::where('kind', 'textbook')->count());
        $this->seed(DemoLibrarySeeder::class);   // دوباره امن
        $this->actingAs($this->librarian)->get(route('library.catalog.index'))->assertOk();
        $this->actingAs($this->librarian)->get(route('library.reports.index'))->assertOk();
    }
}
