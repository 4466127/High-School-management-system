<?php

namespace Database\Seeders;

use App\Enums\EnrollmentStatus;
use App\Enums\StaffType;
use App\Enums\StudentStatus;
use App\Models\AcademicYear;
use App\Models\Enrollment;
use App\Models\GradeLevel;
use App\Models\Section;
use App\Models\Staff;
use App\Models\Student;
use App\Models\Subject;
use App\Models\User;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;
use RuntimeException;

/**
 * فقط برای توسعه: سال ۱۴۰۵، مضامین هر صنف، بخش‌ها، معلمان مضامین و شمولیت دانش‌آموزان ساختگی.
 * اگر سال تحصیلی‌ای وجود داشته باشد، هیچ کاری نمی‌کند. کاربر demo.teacher به یک معلم وصل می‌شود.
 */
class DemoAcademicSeeder extends Seeder
{
    private const PRIMARY = ['قرآن کریم', 'تعلیمات اسلامی', 'دری', 'پشتو', 'ریاضی', 'ساینس', 'خط و رسم', 'تربیت بدنی'];

    private const MIDDLE = ['قرآن کریم', 'تعلیمات اسلامی', 'دری', 'پشتو', 'انگلیسی', 'ریاضی', 'فزیک', 'کیمیا', 'بیولوژی', 'تاریخ', 'جغرافیه', 'تعلیمات مدنی'];

    private const HIGH = ['قرآن کریم', 'تعلیمات اسلامی', 'دری', 'پشتو', 'انگلیسی', 'ریاضی', 'فزیک', 'کیمیا', 'بیولوژی', 'تاریخ', 'جغرافیه', 'کمپیوتر'];

    public function run(): void
    {
        if (app()->isProduction()) {
            throw new RuntimeException('DemoAcademicSeeder must not run in production.');
        }
        if (AcademicYear::exists()) {
            $this->command?->info('Academic years already exist; demo academic data skipped.');

            return;
        }

        DB::transaction(function () {
            $year = AcademicYear::create(['name' => '1405', 'start_date' => '2026-03-21', 'end_date' => '2026-12-21', 'status' => 'open']);
            $year->forceFill(['is_current' => true])->save();
            AcademicYear::forgetCurrent();
            $year->terms()->create(['name' => 'چهارونیم‌ماهه‌ی اول', 'start_date' => '2026-03-21', 'end_date' => '2026-08-05', 'sort_order' => 1]);
            $year->terms()->create(['name' => 'چهارونیم‌ماهه‌ی دوم', 'start_date' => '2026-08-06', 'end_date' => '2026-12-21', 'sort_order' => 2]);

            $subjects = Subject::pluck('id', 'name');
            $teachers = Staff::where('staff_type', StaffType::Teacher)->pluck('id')->values();
            $grades = GradeLevel::orderBy('sort_order')->get();

            foreach ($grades as $grade) {
                $names = $grade->sort_order <= 6 ? self::PRIMARY : ($grade->sort_order <= 9 ? self::MIDDLE : self::HIGH);
                $grade->subjects()->sync(collect($names)->values()->mapWithKeys(fn ($n, $i) => [$subjects[$n] => ['sort_order' => $i + 1]])->all());
            }

            $sections = collect();
            foreach ($grades as $i => $grade) {
                $sections->push(Section::create([
                    'academic_year_id' => $year->id, 'grade_level_id' => $grade->id, 'name' => 'الف', 'shift' => 'morning',
                    'class_teacher_id' => $teachers->isNotEmpty() ? $teachers[$i % $teachers->count()] : null, 'capacity' => 40,
                ]));
                if (in_array($grade->sort_order, [7, 10], true)) {
                    $sections->push(Section::create([
                        'academic_year_id' => $year->id, 'grade_level_id' => $grade->id, 'name' => 'ب', 'shift' => 'afternoon', 'capacity' => 40,
                    ]));
                }
            }

            foreach ($sections as $s => $section) {
                foreach ($section->gradeLevel->subjects as $k => $subject) {
                    if ($teachers->isNotEmpty()) {
                        $section->assignments()->create(['subject_id' => $subject->id, 'staff_id' => $teachers[($s + $k) % $teachers->count()]]);
                    }
                }
            }

            $students = Student::where('status', StudentStatus::Active)->orderBy('id')->get();
            $roll = [];
            foreach ($students as $n => $student) {
                $section = $sections[$n % $sections->count()];
                $roll[$section->id] = ($roll[$section->id] ?? 0) + 1;
                Enrollment::create([
                    'student_id' => $student->id, 'academic_year_id' => $year->id, 'section_id' => $section->id,
                    'roll_no' => $roll[$section->id], 'enrolled_on' => '2026-03-21', 'status' => EnrollmentStatus::Active,
                ]);
            }

            // کاربر آزمایشی معلم به نگران صنف اول وصل می‌شود (برای آزمایش محدوده‌ی معلم)
            $demoTeacher = User::where('username', 'demo.teacher')->first();
            if ($demoTeacher && ! $demoTeacher->staff_id && $sections->first()->class_teacher_id) {
                $demoTeacher->forceFill(['staff_id' => $sections->first()->class_teacher_id])->save();
            }
        });

        $this->command?->info('Demo academic data: year 1405, '.Section::count().' sections, '.Enrollment::count().' enrollments.');
    }
}
