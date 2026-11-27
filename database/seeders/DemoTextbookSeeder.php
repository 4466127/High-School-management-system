<?php

namespace Database\Seeders;

use App\Enums\BookCategory;
use App\Enums\CopyStatus;
use App\Enums\EnrollmentStatus;
use App\Models\AcademicYear;
use App\Models\Book;
use App\Models\BookCopy;
use App\Models\Enrollment;
use App\Models\GradeLevel;
use App\Models\User;
use App\Services\AttendanceService;
use App\Services\LibraryService;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;
use RuntimeException;

/**
 * فقط برای توسعه و آزمایش (1.8.1): برای هر صنفی که در سال جاری بخش دارد و هنوز هیچ کتاب درسی ندارد،
 * یک کتاب درسی برای هر مضمون صنف (وزارت معارف، جلد به اندازه‌ی بزرگ‌ترین بخش + ۲).
 * تا «توزیع کتاب‌های درسی» برای هر صنف کتاب‌های همان صنف را نشان دهد. اجرای دوباره امن است.
 */
class DemoTextbookSeeder extends Seeder
{
    public function run(): void
    {
        if (app()->isProduction()) {
            throw new RuntimeException('DemoTextbookSeeder must not run in production.');
        }
        $year = AcademicYear::current();
        $user = User::where('username', 'demo.librarian')->first() ?? User::where('username', 'admin')->first() ?? User::first();
        if (! $year || ! $user) {
            return;
        }
        $today = AttendanceService::today()->toDateString();
        $created = 0;

        DB::transaction(function () use ($year, $user, $today, &$created) {
            $acc = (int) app(LibraryService::class)->nextAccession() - 1;
            $grades = GradeLevel::with('subjects')->whereHas('sections', fn ($q) => $q->where('academic_year_id', $year->id))->orderBy('sort_order')->get();
            foreach ($grades as $grade) {
                if (Book::where('category', BookCategory::Textbook->value)->where('grade_level_id', $grade->id)->exists()) {
                    continue;
                }
                $size = Enrollment::whereHas('section', fn ($q) => $q->where('academic_year_id', $year->id)->where('grade_level_id', $grade->id))
                    ->where('status', EnrollmentStatus::Active)->selectRaw('count(*) as n')->groupBy('section_id')->get()->max('n') ?? 20;
                foreach ($grade->subjects as $subject) {
                    $book = new Book(['title' => $subject->name.' — '.$grade->name, 'publisher' => 'وزارت معارف (نمونه)', 'publish_year' => 1399,
                        'category' => BookCategory::Textbook->value, 'language' => 'dari', 'grade_level_id' => $grade->id, 'subject_id' => $subject->id,
                        'call_number' => 'درسی-'.$grade->sort_order]);
                    $book->created_by = $user->id;
                    $book->save();
                    for ($i = 0; $i < (int) $size + 2; $i++) {
                        $copy = new BookCopy(['condition' => 'new', 'source' => 'ministry', 'acquired_on' => $today]);
                        $copy->forceFill(['book_id' => $book->id, 'accession_no' => (string) ++$acc, 'status' => CopyStatus::Available, 'created_by' => $user->id])->save();
                    }
                    $created++;
                }
            }
        });
        $this->command?->info($created ? "Demo textbooks: {$created} created." : 'Demo textbooks: every grade already has textbooks.');
    }
}
