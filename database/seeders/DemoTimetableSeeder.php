<?php

namespace Database\Seeders;

use App\Enums\RoomType;
use App\Http\Controllers\Timetable\SetupController;
use App\Models\AcademicYear;
use App\Models\Period;
use App\Models\Room;
use App\Models\Section;
use App\Models\TeacherAssignment;
use App\Models\TimetableEntry;
use App\Models\User;
use App\Support\SchoolCalendar;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;
use RuntimeException;

/**
 * فقط برای توسعه و آزمایش (مرحله‌ی ۱۵): ساعت‌های پیش‌فرض صبح و بعدازظهر، یک اتاق برای هر بخش و یک لابراتوار،
 * و تقسیم اوقات نمونه برای همه‌ی بخش‌های سال جاری — بدون تداخل معلم و اتاق (معلم مشغول ← خانه بدون معلم).
 * اگر تقسیم اوقاتی وجود داشته باشد، هیچ کاری نمی‌کند.
 */
class DemoTimetableSeeder extends Seeder
{
    public function run(): void
    {
        if (app()->isProduction()) {
            throw new RuntimeException('DemoTimetableSeeder must not run in production.');
        }
        $year = AcademicYear::current();
        if (! $year || TimetableEntry::exists()) {
            $this->command?->info('Timetable already exists (or no current year); demo timetable skipped.');

            return;
        }
        $userId = User::where('username', 'demo.office')->value('id') ?? User::where('username', 'admin')->value('id');

        DB::transaction(function () use ($year, $userId) {
            foreach (SetupController::DEFAULTS as $shift => $list) {
                if (Period::where('shift', $shift)->exists()) {
                    continue;
                }
                foreach ($list as $i => $p) {
                    Period::create(['name' => $p[0], 'shift' => $shift, 'start_time' => $p[1], 'end_time' => $p[2], 'sort_order' => $i + 1, 'is_break' => $p[3] ?? false]);
                }
            }

            $sections = Section::with('gradeLevel.subjects')->where('academic_year_id', $year->id)->ordered()->get();
            Room::firstOrCreate(['name' => 'لابراتوار ساینس'], ['type' => RoomType::Lab, 'capacity' => 30, 'notes' => 'نمونه']);
            $rooms = [];
            foreach ($sections as $i => $section) {
                $name = trim((string) $section->room) !== '' ? (string) $section->room : 'اتاق '.($i + 1);
                $rooms[$section->id] = Room::firstOrCreate(['name' => $name], ['type' => RoomType::Classroom, 'capacity' => 40])->id;
            }

            $days = SchoolCalendar::schoolDays();
            $busy = [];   // "staff|day|period" و "room|day|period"
            foreach ($sections as $s => $section) {
                $subjects = $section->gradeLevel->subjects->pluck('id')->values();
                if ($subjects->isEmpty()) {
                    continue;
                }
                $teachers = TeacherAssignment::where('section_id', $section->id)->pluck('staff_id', 'subject_id');
                $periods = Period::where('shift', $section->shift)->where('is_break', false)->ordered()->get();
                $n = $s; // جابه‌جایی آغاز تا بخش‌ها هم‌زمان یک مضمون نداشته باشند
                foreach ($days as $day) {
                    foreach ($periods as $period) {
                        $subject = $subjects[$n++ % $subjects->count()];
                        $staff = $teachers[$subject] ?? null;
                        if ($staff && isset($busy["s|$staff|$day|{$period->id}"])) {
                            $staff = null;
                        }
                        $room = isset($busy["r|{$rooms[$section->id]}|$day|{$period->id}"]) ? null : $rooms[$section->id];
                        $entry = new TimetableEntry(['subject_id' => $subject, 'staff_id' => $staff, 'room_id' => $room]);
                        $entry->forceFill([
                            'academic_year_id' => $year->id, 'section_id' => $section->id, 'day' => $day, 'period_id' => $period->id,
                            'created_by' => $userId, 'updated_by' => $userId,
                        ])->save();
                        if ($staff) {
                            $busy["s|$staff|$day|{$period->id}"] = true;
                        }
                        if ($room) {
                            $busy["r|$room|$day|{$period->id}"] = true;
                        }
                    }
                }
            }
        });
        $this->command?->info('Demo periods, rooms and timetable created.');
    }
}
