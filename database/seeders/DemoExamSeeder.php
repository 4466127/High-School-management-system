<?php

namespace Database\Seeders;

use App\Enums\EnrollmentStatus;
use App\Models\AcademicYear;
use App\Models\Exam;
use App\Models\ExamType;
use App\Models\Section;
use App\Models\User;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;
use RuntimeException;

/**
 * فقط برای توسعه: فعالیت صنفی و کارخانگی (۲۰، باز)، امتحان چهارونیم‌ماهه (۳۰، قفل) و امتحان سالانه (۵۰، باز).
 * نمره‌ی سالانه برای همه‌ی بخش‌ها به جز بخش اول ثبت می‌شود، تا ثبت نمره و نهایی‌کردن هر دو آزمایش شوند.
 * اگر امتحانی وجود داشته باشد، هیچ کاری نمی‌کند.
 */
class DemoExamSeeder extends Seeder
{
    public function run(): void
    {
        if (app()->isProduction()) {
            throw new RuntimeException('DemoExamSeeder must not run in production.');
        }
        $year = AcademicYear::current();
        if (! $year || Exam::exists()) {
            $this->command?->info('Exams already exist (or no current year); demo exams skipped.');

            return;
        }

        mt_srand(6);
        $admin = User::where('username', 'admin')->value('id');
        $terms = $year->terms()->get();
        $type = fn (string $name) => ExamType::where('name', $name)->value('id') ?? ExamType::value('id');

        DB::transaction(function () use ($year, $terms, $type, $admin) {
            $activity = Exam::create([
                'academic_year_id' => $year->id, 'exam_type_id' => $type('فعالیت صنفی و کارخانگی'),
                'name' => 'فعالیت صنفی و کارخانگی', 'start_date' => $year->start_date, 'end_date' => $year->end_date,
                'max_mark' => 20, 'weight' => 20, 'counts_in_final' => true, 'status' => 'open',
            ]);
            $mid = Exam::create([
                'academic_year_id' => $year->id, 'academic_term_id' => $terms->get(0)?->id, 'exam_type_id' => $type('امتحان چهارونیم‌ماهه'),
                'name' => 'امتحان چهارونیم‌ماهه', 'start_date' => '2026-07-20', 'end_date' => '2026-07-30',
                'max_mark' => 30, 'weight' => 30, 'counts_in_final' => true, 'status' => 'locked',
            ]);
            $final = Exam::create([
                'academic_year_id' => $year->id, 'academic_term_id' => $terms->get(1)?->id, 'exam_type_id' => $type('امتحان سالانه'),
                'name' => 'امتحان سالانه', 'start_date' => '2026-12-01', 'end_date' => '2026-12-15',
                'max_mark' => 50, 'weight' => 50, 'counts_in_final' => true, 'status' => 'open',
            ]);

            $sections = Section::where('academic_year_id', $year->id)
                ->with(['gradeLevel.subjects', 'enrollments' => fn ($q) => $q->where('status', EnrollmentStatus::Active)])
                ->orderBy('id')
                ->get();
            $now = now();

            foreach ($sections as $s => $section) {
                $rows = [];
                foreach ($section->enrollments as $enrollment) {
                    $ability = mt_rand(30, 95);   // فیصدی تقریبی هر دانش‌آموز
                    foreach ($section->gradeLevel->subjects as $subject) {
                        foreach ([[$activity, 20], [$mid, 30], [$final, 50]] as [$exam, $max]) {
                            if ($exam->is($final) && $s === 0) {
                                continue;   // بخش اول: سالانه را خودتان ثبت کنید
                            }
                            $absent = mt_rand(1, 150) === 1;
                            $mark = $absent ? null : max(0, min($max, round(($ability + mt_rand(-15, 15)) * $max / 100 * 2) / 2));
                            $rows[] = [
                                'exam_id' => $exam->id, 'enrollment_id' => $enrollment->id, 'subject_id' => $subject->id,
                                'mark' => $mark, 'is_absent' => $absent, 'note' => null,
                                'entered_by' => $admin, 'updated_by' => $admin, 'created_at' => $now, 'updated_at' => $now,
                            ];
                        }
                    }
                }
                foreach (array_chunk($rows, 500) as $chunk) {
                    DB::table('marks')->insert($chunk);
                }
            }
        });

        $this->command?->info('Demo exams: 2 exams, '.DB::table('marks')->count().' marks.');
    }
}
