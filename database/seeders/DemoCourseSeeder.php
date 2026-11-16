<?php

namespace Database\Seeders;

use App\Enums\AttendanceStatus;
use App\Enums\CourseChannel;
use App\Enums\CourseEntryType;
use App\Enums\CourseSeason;
use App\Enums\CourseStatus;
use App\Enums\Shift;
use App\Models\AcademicYear;
use App\Models\Course;
use App\Models\Enrollment;
use App\Models\Staff;
use App\Models\User;
use App\Services\AttendanceService;
use App\Services\CourseLedgerService;
use App\Services\CourseService;
use App\Support\Money;
use Illuminate\Database\Seeder;
use RuntimeException;

/**
 * فقط برای توسعه: کورس زمستانی انگلیسی (گروه قبل و بعد از ظهر) و کورس کامپیوتر در جریان سال،
 * با دانش‌آموزان مکتب، دو فرد بیرونی، حاضری چند روز گذشته و دفتر مالی (کمک، کمک مستقیم، معاش، مصرف).
 * اگر کورسی هست، کاری نمی‌کند.
 */
class DemoCourseSeeder extends Seeder
{
    public function run(CourseService $courses, CourseLedgerService $ledger): void
    {
        if (app()->isProduction()) {
            throw new RuntimeException('DemoCourseSeeder must not run in production.');
        }
        $admin = User::where('username', 'admin')->first();
        $year = AcademicYear::current();
        $teachers = Staff::query()->where('staff_type', 'teacher')->where('employment_status', 'active')->orderBy('id')->limit(3)->get();
        if (! $admin || ! $year || $teachers->count() < 2 || Course::exists()) {
            $this->command?->info('Courses already exist (or no admin/year/teachers); demo courses skipped.');

            return;
        }

        $today = AttendanceService::today();
        $students = Enrollment::query()->where('academic_year_id', $year->id)->where('status', 'active')->inRandomOrder(11)->limit(24)->pluck('student_id')->all();

        // ---- کورس انگلیسی (زمستانی، دو نوبت)
        $english = $courses->saveCourse(null, [
            'name' => 'کورس زمستانی انگلیسی', 'field' => 'انگلیسی', 'season' => CourseSeason::Winter,
            'start_date' => $today->subDays(20)->toDateString(), 'end_date' => $today->addDays(60)->toDateString(),
            'status' => CourseStatus::Active, 'description' => 'با کمک مردم قریه؛ سطح مبتدی و متوسط.',
        ], $admin);
        $morning = $courses->saveGroup($english, null, ['name' => 'سطح مبتدی', 'shift' => Shift::Morning, 'staff_id' => $teachers[0]->id, 'schedule' => 'شنبه تا چهارشنبه، ۸ تا ۱۰', 'is_active' => true], $admin);
        $afternoon = $courses->saveGroup($english, null, ['name' => 'سطح متوسط', 'shift' => Shift::Afternoon, 'staff_id' => $teachers[1]->id, 'schedule' => 'شنبه تا چهارشنبه، ۲ تا ۴', 'is_active' => true], $admin);
        $courses->addStudents($morning, array_slice($students, 0, 10), $english->start_date->toDateString(), $admin);
        $courses->addStudents($afternoon, array_slice($students, 10, 8), $english->start_date->toDateString(), $admin);
        $courses->addExternal($afternoon, ['name' => 'فریدون رحیمی', 'father_name' => 'عبدالکریم', 'phone' => '0700000001', 'gender' => 'male', 'joined_on' => $english->start_date->toDateString()], $admin);

        // ---- کورس کامپیوتر (در جریان سال)
        $computer = $courses->saveCourse(null, [
            'name' => 'کورس کامپیوتر', 'field' => 'کامپیوتر', 'season' => CourseSeason::SchoolYear,
            'start_date' => $today->subDays(10)->toDateString(), 'end_date' => null, 'status' => CourseStatus::Active,
            'description' => 'آشنایی با Word و Excel؛ بعد از ظهر.',
        ], $admin);
        $pc = $courses->saveGroup($computer, null, ['name' => 'گروه اول', 'shift' => Shift::Afternoon, 'staff_id' => ($teachers[2] ?? $teachers[0])->id, 'schedule' => 'یکشنبه و سه‌شنبه، ۱ تا ۳', 'is_active' => true], $admin);
        $courses->addStudents($pc, array_slice($students, 18, 6), $computer->start_date->toDateString(), $admin);
        $courses->addExternal($pc, ['name' => 'مریم سادات', 'father_name' => 'سید احمد', 'gender' => 'female', 'joined_on' => $computer->start_date->toDateString()], $admin);

        // ---- حاضری پنج روز گذشته
        mt_srand(5);
        foreach ([$morning, $afternoon, $pc] as $group) {
            for ($d = 5; $d >= 1; $d--) {
                $date = $today->subDays($d);
                if ($date->lt($group->course->start_date)) {
                    continue;
                }
                $statuses = [];
                foreach ($courses->sheetParticipants($group, null) as $id => $_) {
                    $r = mt_rand(1, 100);
                    $statuses[$id] = ($r <= 82 ? AttendanceStatus::Present : ($r <= 90 ? AttendanceStatus::Absent : ($r <= 95 ? AttendanceStatus::Late : AttendanceStatus::Sick)))->value;
                }
                $courses->saveAttendance($group, $date, $statuses, [], $admin);
            }
        }

        // ---- دفتر مالی
        $entry = fn (Course $course, CourseEntryType $type, int $afn, int $daysAgo, ?string $donor = null, ?int $staffId = null, CourseChannel $channel = CourseChannel::Cashbox, ?string $desc = null) => $ledger->record($course, [
            'entry_type' => $type, 'channel' => $channel, 'staff_id' => $staffId, 'amount_cents' => Money::toCents($afn),
            'date' => $today->subDays($daysAgo)->toDateString(), 'donor_name' => $donor, 'description' => $desc,
        ], $admin);

        $entry($english, CourseEntryType::Donation, 15000, 19, 'حاجی عبدالله', desc: 'کمک برای معاش زمستان');
        $entry($english, CourseEntryType::Donation, 5000, 15, null, desc: 'کمک بی‌نام');
        $entry($english, CourseEntryType::Donation, 3000, 12, 'شورای قریه', $teachers[1]->id, CourseChannel::DirectToTeacher);
        $entry($english, CourseEntryType::Expense, 1800, 18, desc: 'کتاب و کاغذ');
        $entry($english, CourseEntryType::TeacherSalary, 6000, 2, staffId: $teachers[0]->id, desc: 'معاش ماه اول');
        $entry($computer, CourseEntryType::Donation, 8000, 9, 'جوانان قریه');
        $entry($computer, CourseEntryType::Expense, 2500, 8, desc: 'ترمیم کمپیوتر');

        $this->command?->info('Demo courses: 2 courses, 3 groups, attendance and ledger entries.');
    }
}
