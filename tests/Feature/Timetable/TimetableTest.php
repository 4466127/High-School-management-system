<?php

namespace Tests\Feature\Timetable;

use App\Enums\Role;
use App\Models\ActivityLog;
use App\Models\Period;
use App\Models\Room;
use App\Models\Setting;
use App\Models\Subject;
use App\Models\TimetableEntry;
use Database\Seeders\DemoTimetableSeeder;
use Database\Seeders\RolePermissionSeeder;
use Tests\Feature\Exams\ExamTestCase;

/** مرحله‌ی ۱۵: تقسیم اوقات — ساعت‌ها، اتاق‌ها، خانه‌ها، جلوگیری از تداخل، جدول معلم و اتاق، چاپ، داشبورد */
class TimetableTest extends ExamTestCase
{
    private Period $first;

    private Period $second;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(RolePermissionSeeder::class);
        Setting::put('weekly_holidays', 'friday');
        $this->actingAs($this->principal)->post(route('timetable.setup.periods.defaults'), ['shift' => 'morning'])->assertSessionHasNoErrors();
        $this->first = Period::where('shift', 'morning')->where('sort_order', 1)->firstOrFail();
        $this->second = Period::where('shift', 'morning')->where('sort_order', 2)->firstOrFail();
    }

    private function cell(array $data, $section = null)
    {
        return $this->actingAs($this->principal)->post(route('timetable.sections.cell', $section ?? $this->section), $data + ['day' => 'saturday', 'period_id' => $this->first->id]);
    }

    public function test_default_periods_and_rooms_setup(): void
    {
        $this->assertSame(7, Period::where('shift', 'morning')->count());
        $this->assertTrue(Period::where('is_break', true)->exists());
        $this->actingAs($this->principal)->post(route('timetable.setup.periods.defaults'), ['shift' => 'morning'])->assertSessionHas('warning');

        $this->actingAs($this->principal)->post(route('timetable.setup.rooms.store'), ['name' => 'لابراتوار کیمیا', 'type' => 'lab', 'capacity' => 30])->assertSessionHasNoErrors();
        $this->actingAs($this->principal)->post(route('timetable.setup.rooms.store'), ['name' => 'لابراتوار کیمیا', 'type' => 'lab'])->assertSessionHasErrors('name');
        $this->actingAs($this->principal)->put(route('timetable.setup.periods.update', $this->first), [
            'name' => 'ساعت اول', 'shift' => 'morning', 'start_time' => '09:00', 'end_time' => '08:00', 'sort_order' => 1,
        ])->assertSessionHasErrors('end_time');

        $this->actingAs($this->principal)->get(route('timetable.setup.index'))->assertOk()->assertSee('لابراتوار کیمیا')->assertSee('ساعت ششم');
        $this->assertTrue(ActivityLog::where('action', 'timetable.room_created')->exists());
    }

    public function test_cell_save_update_and_clear(): void
    {
        $teacherStaff = $this->teacher->staff_id;
        $this->cell(['subject_id' => $this->math->id, 'staff_id' => $teacherStaff, 'note' => 'عملی'])->assertSessionHasNoErrors();
        $entry = TimetableEntry::firstOrFail();
        $this->assertSame($this->year->id, $entry->academic_year_id);
        $this->assertSame('عملی', $entry->note);

        $this->cell(['subject_id' => $this->dari->id])->assertSessionHasNoErrors();
        $this->assertSame(1, TimetableEntry::count());
        $this->assertSame($this->dari->id, $entry->fresh()->subject_id);

        $page = $this->actingAs($this->principal)->get(route('timetable.sections.index', ['section' => $this->section->id]));
        $page->assertOk()->assertSee('data-tt-cell', false)->assertSee($this->dari->name)->assertSee(__('timetable.subjects_missing', ['subjects' => $this->math->name]));

        $this->cell(['subject_id' => $this->dari->id, 'clear' => '1'])->assertSessionHasNoErrors();
        $this->assertSame(0, TimetableEntry::count());
        $this->assertTrue(ActivityLog::where('action', 'timetable.cleared')->exists());
    }

    public function test_conflicts_and_invalid_cells_are_rejected(): void
    {
        $staff = $this->teacher->staff_id;
        $room = Room::create(['name' => 'اتاق ۱', 'type' => 'classroom']);
        $this->otherSection->gradeLevel->subjects()->syncWithoutDetaching([$this->math->id => ['sort_order' => 1]]);

        $this->cell(['subject_id' => $this->math->id, 'staff_id' => $staff, 'room_id' => $room->id])->assertSessionHasNoErrors();
        // همان معلم، همان ساعت، بخش دیگر
        $this->cell(['subject_id' => $this->math->id, 'staff_id' => $staff], $this->otherSection)->assertSessionHasErrors('staff_id');
        // همان اتاق
        $this->cell(['subject_id' => $this->math->id, 'room_id' => $room->id], $this->otherSection)->assertSessionHasErrors('room_id');
        // ساعت دیگر آزاد است
        $this->cell(['subject_id' => $this->math->id, 'staff_id' => $staff, 'room_id' => $room->id, 'period_id' => $this->second->id], $this->otherSection)->assertSessionHasNoErrors();

        // رخصتی، تفریح، مضمون بیرون از صنف
        $this->cell(['subject_id' => $this->math->id, 'day' => 'friday'])->assertSessionHasErrors('day');
        $break = Period::where('is_break', true)->firstOrFail();
        $this->cell(['subject_id' => $this->math->id, 'period_id' => $break->id])->assertSessionHasErrors('period_id');
        $other = Subject::whereNotIn('id', [$this->math->id, $this->dari->id])->firstOrFail();
        $this->cell(['subject_id' => $other->id, 'period_id' => $this->second->id])->assertSessionHasErrors('subject_id');

        $this->assertSame(2, TimetableEntry::count());
    }

    public function test_copy_teacher_and_room_views_and_print(): void
    {
        $staff = $this->teacher->staff_id;
        $room = Room::create(['name' => 'اتاق ۲', 'type' => 'classroom']);
        $this->cell(['subject_id' => $this->math->id, 'staff_id' => $staff, 'room_id' => $room->id])->assertSessionHasNoErrors();
        $this->cell(['subject_id' => $this->dari->id, 'period_id' => $this->second->id])->assertSessionHasNoErrors();

        // کاپی به بخش «ب»: معلم ریاضی همان ساعت مشغول است ← بدون معلم؛ اتاق کاپی نمی‌شود
        $this->otherSection->assignments()->create(['subject_id' => $this->math->id, 'staff_id' => $staff]);
        $this->actingAs($this->principal)->post(route('timetable.sections.copy', $this->otherSection), ['from' => $this->section->id])->assertSessionHasNoErrors();
        $copied = TimetableEntry::where('section_id', $this->otherSection->id)->get();
        $this->assertCount(2, $copied);
        $this->assertNull($copied->firstWhere('subject_id', $this->math->id)->staff_id);
        $this->assertNull($copied->firstWhere('subject_id', $this->math->id)->room_id);

        $this->actingAs($this->principal)->get(route('timetable.teachers.index', ['staff' => $staff]))->assertOk()
            ->assertSee($this->section->gradeLevel->name)->assertSee(__('timetable.hours_per_week', ['count' => '۱']));
        $this->actingAs($this->principal)->get(route('timetable.rooms.index', ['room' => $room->id]))->assertOk()->assertSee($this->math->name);
        $this->actingAs($this->principal)->get(route('timetable.sections.index', ['section' => $this->section->id, 'print' => 1]))->assertOk()->assertDontSee('data-tt-cell', false);
        $this->actingAs($this->principal)->get(route('timetable.sections.index', ['all' => 1, 'print' => 1]))->assertOk()->assertSee($this->otherSection->label);
    }

    public function test_teacher_sees_own_timetable_read_only_and_dashboard(): void
    {
        Setting::put('weekly_holidays', '');
        $this->cell(['subject_id' => $this->math->id, 'staff_id' => $this->teacher->staff_id, 'day' => strtolower(now()->format('l'))])->assertSessionHasNoErrors();

        $this->actingAs($this->teacher)->get(route('timetable.teachers.index'))->assertOk()->assertSee($this->math->name);
        $this->actingAs($this->teacher)->get(route('timetable.sections.index', ['section' => $this->section->id]))->assertOk()->assertDontSee('data-tt-cell', false);
        $this->actingAs($this->teacher)->get(route('timetable.sections.index', ['section' => $this->otherSection->id]))->assertForbidden();
        $this->actingAs($this->teacher)->post(route('timetable.sections.cell', $this->section), ['day' => 'saturday', 'period_id' => $this->first->id, 'subject_id' => $this->math->id])->assertForbidden();
        $this->actingAs($this->teacher)->get(route('timetable.setup.index'))->assertForbidden();
        $this->actingAs($this->teacher)->get(route('timetable.rooms.index'))->assertForbidden();

        $this->actingAs($this->teacher)->get(route('dashboard'))->assertOk()->assertSee(__('timetable.today_title'))->assertSee($this->math->name);
        $accountant = $this->userWithRole(Role::Accountant);
        $this->actingAs($accountant)->get(route('timetable.sections.index'))->assertForbidden();
    }

    public function test_demo_seeder_builds_conflict_free_timetable(): void
    {
        $this->section->assignments()->create(['subject_id' => $this->dari->id, 'staff_id' => $this->teacher->staff_id]);
        $this->otherSection->gradeLevel->subjects()->syncWithoutDetaching([$this->math->id => ['sort_order' => 1]]);
        $this->otherSection->assignments()->create(['subject_id' => $this->math->id, 'staff_id' => $this->teacher->staff_id]);
        $this->seed(DemoTimetableSeeder::class);

        $this->assertGreaterThan(0, TimetableEntry::where('section_id', $this->section->id)->count());
        $clash = TimetableEntry::whereNotNull('staff_id')->selectRaw('staff_id, day, period_id, count(*) n')->groupBy('staff_id', 'day', 'period_id')->having('n', '>', 1)->count();
        $this->assertSame(0, $clash);
    }
}
