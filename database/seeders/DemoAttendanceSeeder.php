<?php

namespace Database\Seeders;

use App\Enums\EmploymentStatus;
use App\Enums\EnrollmentStatus;
use App\Models\AcademicYear;
use App\Models\AttendanceSession;
use App\Models\Section;
use App\Models\Staff;
use App\Models\User;
use App\Services\AttendanceService;
use App\Support\SchoolCalendar;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;
use RuntimeException;

/**
 * فقط برای توسعه: حاضری ساختگی ۴ هفته‌ی گذشته (بدون امروز، تا ثبت امروز آزمایش شود) برای همه‌ی بخش‌ها و کارکنان.
 * اگر حاضری‌ای وجود داشته باشد، هیچ کاری نمی‌کند. چند دانش‌آموز غیابت زیاد دارند تا فهرست غیابت زیاد خالی نباشد.
 */
class DemoAttendanceSeeder extends Seeder
{
    public function run(): void
    {
        if (app()->isProduction()) {
            throw new RuntimeException('DemoAttendanceSeeder must not run in production.');
        }
        $year = AcademicYear::current();
        if (! $year || AttendanceSession::exists() || DB::table('staff_attendance')->exists()) {
            $this->command?->info('Attendance already exists (or no current year); demo attendance skipped.');

            return;
        }

        mt_srand(1405);
        $admin = User::where('username', 'admin')->value('id');
        $today = AttendanceService::today();
        $days = [];
        for ($d = $today->subDays(28); $d->lt($today); $d = $d->addDay()) {
            if (! SchoolCalendar::isWeeklyHoliday($d) && $d->gte($year->start_date) && $d->lte($year->end_date)) {
                $days[] = $d;
            }
        }

        DB::transaction(function () use ($year, $days, $admin) {
            $sections = Section::where('academic_year_id', $year->id)
                ->with(['activeEnrollments' => fn ($q) => $q->orderBy('roll_no')])
                ->get();

            foreach ($days as $day) {
                $date = $day->format('Y-m-d');
                foreach ($sections as $section) {
                    if ($section->activeEnrollments->isEmpty()) {
                        continue;
                    }
                    $taken = $day->setTime(8, mt_rand(0, 40));
                    $sessionId = DB::table('attendance_sessions')->insertGetId([
                        'section_id' => $section->id, 'date' => $date, 'taken_by' => $admin, 'taken_at' => $taken,
                        'created_at' => $taken, 'updated_at' => $taken,
                    ]);

                    $rows = [];
                    foreach ($section->activeEnrollments as $i => $enrollment) {
                        if ($enrollment->status !== EnrollmentStatus::Active) {
                            continue;
                        }
                        // نفر دوم هر بخش با شماره‌ی زوج: غیابت زیاد
                        $status = $this->pick($i === 1 && $section->id % 2 === 0 ? 45 : 6);
                        $rows[] = [
                            'attendance_session_id' => $sessionId, 'enrollment_id' => $enrollment->id, 'status' => $status,
                            'note' => $status === 'sick' ? 'مریضی' : null, 'updated_by' => $admin,
                            'created_at' => $taken, 'updated_at' => $taken,
                        ];
                    }
                    DB::table('attendance_records')->insert($rows);
                }

                $staffRows = [];
                foreach (Staff::where('employment_status', '!=', EmploymentStatus::Left)->pluck('employment_status', 'id') as $id => $employment) {
                    $staffRows[] = [
                        'staff_id' => $id, 'date' => $date,
                        'status' => in_array($employment, [EmploymentStatus::OnLeave, EmploymentStatus::OnLeave->value], true) ? 'excused' : $this->pick(3),
                        'note' => null, 'recorded_by' => $admin, 'created_at' => $day->setTime(8, 0), 'updated_at' => $day->setTime(8, 0),
                    ];
                }
                if ($staffRows) {
                    DB::table('staff_attendance')->insert($staffRows);
                }
            }
        });

        $this->command?->info('Demo attendance: '.count($days).' school days, '.AttendanceSession::count().' section sheets.');
    }

    /** وضعیت تصادفی؛ $absentPercent = احتمال «غیرحاضر» */
    private function pick(int $absentPercent): string
    {
        $n = mt_rand(1, 100);

        return match (true) {
            $n <= $absentPercent => 'absent',
            $n <= $absentPercent + 4 => 'late',
            $n <= $absentPercent + 7 => 'excused',
            $n <= $absentPercent + 9 => 'sick',
            default => 'present',
        };
    }
}
