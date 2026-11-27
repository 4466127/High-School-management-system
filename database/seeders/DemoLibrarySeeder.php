<?php

namespace Database\Seeders;

use App\Enums\CopyStatus;
use App\Enums\EnrollmentStatus;
use App\Enums\LoanKind;
use App\Models\AcademicYear;
use App\Models\Book;
use App\Models\BookCopy;
use App\Models\BookLoan;
use App\Models\Enrollment;
use App\Models\GradeLevel;
use App\Models\Section;
use App\Models\Staff;
use App\Models\User;
use App\Services\AttendanceService;
use App\Services\LibraryService;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;
use RuntimeException;

/**
 * فقط برای توسعه و آزمایش (مرحله‌ی ۱۷): کتابخانه‌ی نمونه —
 * کتاب‌های کلاسیک و عمومی (نام‌های ساختگی یا آثار کهن)، کتاب‌های درسی چند صنف، جلدها با نمبر ثبت از ۱،
 * امانت‌های فعال، دیرکرده و بازگشته برای دانش‌آموزان و معلمان، توزیع کتاب درسی برای یک بخش، و چند جلد گم/خراب.
 * اگر کتابی وجود داشته باشد، هیچ کاری نمی‌کند.
 */
class DemoLibrarySeeder extends Seeder
{
    /** [نام، نویسنده، ناشر، سال، دسته، زبان، جای کتاب، جلد، مرجع] — آثار کهن یا نام‌های ساختگی (نمونه) */
    private const BOOKS = [
        ['گلستان', 'سعدی شیرازی', 'انتشارات نمونه', 1396, 'literature', 'dari', 'الف-۱', 4, false],
        ['بوستان', 'سعدی شیرازی', 'انتشارات نمونه', 1396, 'literature', 'dari', 'الف-۱', 3, false],
        ['دیوان حافظ', 'حافظ شیرازی', 'انتشارات نمونه', 1395, 'literature', 'dari', 'الف-۱', 3, false],
        ['شاهنامه (گزیده)', 'ابوالقاسم فردوسی', 'انتشارات نمونه', 1394, 'literature', 'dari', 'الف-۲', 2, false],
        ['مثنوی معنوی (دفتر اول)', 'مولانا جلال‌الدین بلخی', 'انتشارات نمونه', 1397, 'literature', 'dari', 'الف-۲', 2, false],
        ['دیوان خوشحال خان خټک (گزیده)', 'خوشحال خان خټک', 'انتشارات نمونه', 1398, 'literature', 'pashto', 'الف-۳', 2, false],
        ['قصه‌های کوتاه برای نوجوانان (نمونه)', 'نویسنده‌ی نمونه', 'انتشارات نمونه', 1400, 'children', 'dari', 'ب-۱', 4, false],
        ['داستان‌های مصور کودکان (نمونه)', 'نویسنده‌ی نمونه', 'انتشارات نمونه', 1401, 'children', 'pashto', 'ب-۱', 3, false],
        ['تفسیر مختصر قرآن کریم (نمونه)', 'مؤلف نمونه', 'انتشارات نمونه', 1395, 'religion', 'dari', 'ج-۱', 2, false],
        ['سیرت پیامبر (ص) برای جوانان (نمونه)', 'مؤلف نمونه', 'انتشارات نمونه', 1399, 'religion', 'dari', 'ج-۱', 3, false],
        ['قاموس دری به دری (نمونه)', 'گروه مؤلفان', 'انتشارات نمونه', 1390, 'reference', 'dari', 'د-۱', 2, true],
        ['قاموس انگلیسی به دری (نمونه)', 'گروه مؤلفان', 'انتشارات نمونه', 1392, 'reference', 'english', 'د-۱', 2, true],
        ['اطلس جغرافیه‌ی افغانستان (نمونه)', 'گروه مؤلفان', 'انتشارات نمونه', 1398, 'reference', 'dari', 'د-۲', 1, true],
        ['تاریخ مختصر افغانستان (نمونه)', 'مؤرخ نمونه', 'انتشارات نمونه', 1397, 'history', 'dari', 'هـ-۱', 3, false],
        ['جغرافیه‌ی طبیعی (نمونه)', 'مؤلف نمونه', 'انتشارات نمونه', 1396, 'history', 'dari', 'هـ-۱', 2, false],
        ['فزیک به زبان ساده (نمونه)', 'مؤلف نمونه', 'انتشارات نمونه', 1400, 'science', 'dari', 'و-۱', 3, false],
        ['کیمیا برای همه (نمونه)', 'مؤلف نمونه', 'انتشارات نمونه', 1399, 'science', 'dari', 'و-۱', 2, false],
        ['بیولوژی و صحت (نمونه)', 'مؤلف نمونه', 'انتشارات نمونه', 1401, 'technology', 'dari', 'و-۲', 2, false],
        ['حل مسائل الجبر (نمونه)', 'مؤلف نمونه', 'انتشارات نمونه', 1400, 'math', 'dari', 'ز-۱', 4, false],
        ['هندسه‌ی مصور (نمونه)', 'مؤلف نمونه', 'انتشارات نمونه', 1398, 'math', 'dari', 'ز-۱', 2, false],
        ['آموزش کمپیوتر و انترنت (نمونه)', 'مؤلف نمونه', 'انتشارات نمونه', 1402, 'technology', 'dari', 'ز-۲', 3, false],
        ['English Grammar Practice (Sample)', 'Sample Author', 'Sample Press', 2019, 'language', 'english', 'ح-۱', 3, false],
        ['دستور زبان دری (نمونه)', 'مؤلف نمونه', 'انتشارات نمونه', 1397, 'language', 'dari', 'ح-۱', 2, false],
        ['د پښتو ژبې ګرامر (نمونه)', 'مؤلف نمونه', 'انتشارات نمونه', 1398, 'language', 'pashto', 'ح-۲', 2, false],
        ['حقوق شهروندی (نمونه)', 'مؤلف نمونه', 'انتشارات نمونه', 1399, 'social', 'dari', 'ط-۱', 2, false],
        ['رهنمای تدریس فعال (نمونه)', 'مؤلف نمونه', 'انتشارات نمونه', 1400, 'teacher', 'dari', 'ی-۱', 2, false],
        ['روش‌های ارزیابی شاگردان (نمونه)', 'مؤلف نمونه', 'انتشارات نمونه', 1401, 'teacher', 'dari', 'ی-۱', 1, false],
        ['خط و خوشنویسی (نمونه)', 'مؤلف نمونه', 'انتشارات نمونه', 1396, 'arts', 'dari', 'ک-۱', 1, false],
    ];

    public function run(): void
    {
        if (app()->isProduction()) {
            throw new RuntimeException('DemoLibrarySeeder must not run in production.');
        }
        if (Book::exists()) {
            $this->command?->info('Library books already exist; demo library skipped.');

            return;
        }
        $user = User::where('username', 'demo.librarian')->first() ?? User::where('username', 'admin')->first() ?? User::first();
        if (! $user) {
            return;
        }
        $year = AcademicYear::current();
        $today = AttendanceService::today();
        mt_srand(17);

        DB::transaction(function () use ($user, $year, $today) {
            $acc = 0;
            $sources = ['purchase', 'purchase', 'donation', 'ministry'];
            $copiesOf = function (Book $book, int $count, ?string $source = null, ?string $price = null) use (&$acc, $sources, $user, $today) {
                for ($i = 0; $i < $count; $i++) {
                    $copy = new BookCopy(['condition' => $i === 0 ? 'good' : 'new', 'source' => $source ?? $sources[mt_rand(0, 3)],
                        'acquired_on' => $today->subDays(mt_rand(60, 900))->toDateString(), 'price' => $price ?? (string) (mt_rand(10, 60) * 10)]);
                    $copy->forceFill(['book_id' => $book->id, 'accession_no' => (string) ++$acc, 'status' => CopyStatus::Available, 'created_by' => $user->id])->save();
                }
            };

            foreach (self::BOOKS as [$title, $author, $publisher, $pubYear, $cat, $lang, $call, $count, $ref]) {
                $book = new Book(['title' => $title, 'author' => $author, 'publisher' => $publisher, 'publish_year' => $pubYear, 'category' => $cat,
                    'language' => $lang, 'call_number' => $call, 'is_reference' => $ref, 'pages' => mt_rand(8, 45) * 10]);
                $book->created_by = $user->id;
                $book->save();
                $copiesOf($book, $count);
            }

            // کتاب‌های درسی: دو مضمون اول هر صنف دارای بخش، جلد به اندازه‌ی بزرگ‌ترین بخش (وزارت معارف)
            $textbooks = collect();
            if ($year) {
                foreach (GradeLevel::with('subjects')->whereHas('sections', fn ($q) => $q->where('academic_year_id', $year->id))->orderBy('sort_order')->limit(4)->get() as $grade) {
                    $size = Enrollment::whereHas('section', fn ($q) => $q->where('academic_year_id', $year->id)->where('grade_level_id', $grade->id))
                        ->where('status', EnrollmentStatus::Active)->selectRaw('count(*) as n')->groupBy('section_id')->get()->max('n') ?? 20;
                    foreach ($grade->subjects->take(2) as $subject) {
                        $book = new Book(['title' => $subject->name.' — '.$grade->name, 'publisher' => 'وزارت معارف (نمونه)', 'publish_year' => 1399,
                            'category' => 'textbook', 'language' => 'dari', 'grade_level_id' => $grade->id, 'subject_id' => $subject->id, 'call_number' => 'درسی-'.$grade->sort_order]);
                        $book->created_by = $user->id;
                        $book->save();
                        $copiesOf($book, (int) $size + 2, 'ministry', null);
                        $textbooks->push($book);
                    }
                }
            }

            // امانت‌های عادی
            $students = $year ? Enrollment::with('student')->where('academic_year_id', $year->id)->where('status', EnrollmentStatus::Active)->inRandomOrder(17)->limit(60)->get()->pluck('student') : collect();
            $staff = Staff::where('employment_status', 'active')->orderBy('id')->limit(6)->get();
            $lendable = BookCopy::whereHas('book', fn ($q) => $q->where('is_reference', false)->where('category', '!=', 'textbook'))->orderBy('id')->get()->shuffle(17)->values();
            $pick = 0;
            $make = function ($borrower, string $type, int $daysAgo, int $length, ?int $returnedAfter, string $status = 'returned') use (&$pick, $lendable, $user, $today, $year) {
                $copy = $lendable->get($pick++ % max(1, $lendable->count()));
                if (! $copy || ($status === 'active' && $copy->fresh()->status !== CopyStatus::Available)) {
                    return;
                }
                $loan = new BookLoan;
                $loan->forceFill([
                    'book_copy_id' => $copy->id, 'borrower_type' => $type,
                    'student_id' => $type === 'student' ? $borrower->id : null, 'staff_id' => $type === 'staff' ? $borrower->id : null,
                    'academic_year_id' => $year?->id, 'kind' => LoanKind::Loan,
                    'loaned_on' => $today->subDays($daysAgo)->toDateString(), 'due_on' => $today->subDays($daysAgo - $length)->toDateString(),
                    'returned_on' => $returnedAfter !== null ? $today->subDays($daysAgo - $returnedAfter)->toDateString() : null,
                    'status' => $status, 'return_condition' => $status === 'returned' ? 'good' : null,
                    'issued_by' => $user->id, 'closed_by' => $status === 'active' ? null : $user->id,
                ])->save();
                if ($status === 'active') {
                    $copy->forceFill(['status' => CopyStatus::OnLoan])->save();
                }
            };
            foreach ($students->values() as $i => $s) {
                match ($i % 5) {
                    0, 2 => $make($s, 'student', mt_rand(1, 10), 14, null, 'active'),          // فعال
                    1 => $make($s, 'student', mt_rand(18, 35), 14, null, 'active'),            // دیرکرده
                    default => $make($s, 'student', mt_rand(20, 150), 14, mt_rand(5, 20)),     // بازگشته
                };
            }
            foreach ($staff as $i => $s) {
                $make($s, 'staff', mt_rand(3, 20), 30, null, 'active');
                $make($s, 'staff', mt_rand(60, 120), 30, mt_rand(10, 30));
            }

            // یک جلد گم و یک خراب
            foreach (BookCopy::where('status', CopyStatus::Available)->whereHas('book', fn ($q) => $q->where('category', '!=', 'textbook'))->orderByDesc('id')->limit(2)->get() as $k => $copy) {
                $copy->forceFill(['status' => $k === 0 ? CopyStatus::Lost : CopyStatus::Damaged,
                    'status_reason' => $k === 0 ? 'در امانت سال گذشته گم شد (نمونه)' : 'صحافی باز شده؛ برای ترمیم (نمونه)',
                    'status_changed_on' => $today->subDays(12)->toDateString()])->save();
            }

            // توزیع کتاب درسی برای اولین بخش صنف اولین کتاب درسی
            $first = $textbooks->first();
            if ($first && $year) {
                $section = Section::where('academic_year_id', $year->id)->where('grade_level_id', $first->grade_level_id)->orderBy('id')->first();
                if ($section) {
                    app(LibraryService::class)->distributeTextbooks($section, $first, $year->end_date->toDateString(), $user);
                }
            }
        });
        $this->command?->info('Demo library created.');
    }
}
