<?php

namespace App\Services;

use App\Enums\Shift;
use App\Models\AcademicYear;
use App\Models\Period;
use App\Models\Room;
use App\Models\Section;
use App\Models\Staff;
use App\Models\TeacherAssignment;
use App\Models\TimetableEntry;
use App\Models\User;
use App\Support\SchoolCalendar;
use Carbon\Carbon;
use Illuminate\Database\UniqueConstraintViolationException;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

/**
 * تقسیم اوقات (مرحله‌ی ۱۵). هر خانه = بخش + روز + ساعت ← مضمون، معلم، اتاق.
 * جلوگیری از تداخل: معلم یا اتاق در همان سال، روز و ساعت فقط یک جا (اینجا با پیام روشن، در پایگاه داده با کلید یکتا).
 * مضمون باید از مضامین صنف بخش باشد؛ ساعت باید از نوبت همان بخش و درسی (نه تفریح) باشد.
 */
class TimetableService
{
    public function __construct(private readonly ActivityLogger $logger) {}

    /** @return list<string> */
    public function days(): array
    {
        return SchoolCalendar::schoolDays();
    }

    /** ساعت‌های فعال یک نوبت (با تفریح‌ها) */
    public function periods(Shift $shift): Collection
    {
        return Period::query()->where('is_active', true)->where('shift', $shift)->ordered()->get();
    }

    /**
     * ساعت‌های یک نوبت از روی وقت شروع، طول هر ساعت، شمار ساعت‌ها و تفریح (بی پایگاه داده).
     *
     * @return list<array{name: string, start: string, end: string, break: bool}>
     */
    public static function periodPlan(string $start, int $minutes, int $lessons, int $breakAfter, int $breakMinutes): array
    {
        $ordinals = __('timetable.ordinals');
        $t = Carbon::createFromFormat('H:i', $start);
        $plan = [];
        for ($i = 1; $i <= $lessons; $i++) {
            $end = $t->copy()->addMinutes($minutes);
            $plan[] = ['name' => __('timetable.period_n', ['n' => $ordinals[$i - 1] ?? $i]), 'start' => $t->format('H:i'), 'end' => $end->format('H:i'), 'break' => false];
            $t = $end;
            if ($i === $breakAfter && $breakMinutes > 0 && $i < $lessons) {
                $end = $t->copy()->addMinutes($breakMinutes);
                $plan[] = ['name' => __('timetable.break'), 'start' => $t->format('H:i'), 'end' => $end->format('H:i'), 'break' => true];
                $t = $end;
            }
        }

        return $plan;
    }

    /**
     * ساعت‌های یک نوبت را با برنامه‌ی تازه هماهنگ می‌کند. ساعت درسی k-ام همان رکورد می‌ماند (فقط وقتش عوض می‌شود)
     * تا خانه‌های تقسیم اوقات از دست نرود؛ ساعت اضافی غیرفعال می‌شود (حذف نیست).
     *
     * @param  list<array{name: string, start: string, end: string, break: bool}>  $plan
     */
    public function applyPeriodPlan(Shift $shift, array $plan, User $user): void
    {
        DB::transaction(function () use ($shift, $plan, $user) {
            $existing = Period::where('shift', $shift)->ordered()->lockForUpdate()->get();
            $pools = ['lesson' => $existing->where('is_break', false)->values(), 'break' => $existing->where('is_break', true)->values()];
            $used = ['lesson' => 0, 'break' => 0];
            foreach ($plan as $i => $p) {
                $kind = $p['break'] ? 'break' : 'lesson';
                $period = $pools[$kind]->get($used[$kind]++) ?? new Period;
                $period->fill(['name' => $p['name'], 'shift' => $shift, 'start_time' => $p['start'], 'end_time' => $p['end'],
                    'sort_order' => $i + 1, 'is_break' => $p['break'], 'is_active' => true])->save();
            }
            foreach (['lesson', 'break'] as $kind) {
                $pools[$kind]->slice($used[$kind])->each(fn (Period $p) => $p->update(['is_active' => false]));
            }
            $lessons = collect($plan)->where('break', false)->count();
            $this->logger->log('timetable.periods_generated', null, $shift->label().': '.$plan[0]['start'].' – '.end($plan)['end'],
                new: ['lessons' => $lessons, 'periods' => count($plan)], user: $user);
        });
    }

    /**
     * نوبت (قبل/بعد از ظهر) همه‌ی بخش‌های هر صنف در یک سال. بخشی که خانه‌ی تقسیم اوقات دارد عوض نمی‌شود
     * (ساعت‌هایش مال نوبت قبلی است).
     *
     * @param  array<int, Shift>  $shifts  grade_level_id ← نوبت
     * @return array{changed: int, skipped: list<string>}
     */
    public function setGradeShifts(AcademicYear $year, array $shifts, User $user): array
    {
        return DB::transaction(function () use ($year, $shifts, $user) {
            $result = ['changed' => 0, 'skipped' => []];
            $sections = Section::with('gradeLevel')->where('academic_year_id', $year->id)->whereIn('grade_level_id', array_keys($shifts))->lockForUpdate()->get();
            $withEntries = TimetableEntry::whereIn('section_id', $sections->pluck('id'))->distinct()->pluck('section_id')->flip();
            foreach ($sections as $section) {
                $shift = $shifts[$section->grade_level_id];
                if ($section->shift === $shift) {
                    continue;
                }
                if ($withEntries->has($section->id)) {
                    $result['skipped'][] = $section->label;

                    continue;
                }
                $old = $section->shift->label();
                $section->update(['shift' => $shift]);
                $this->logger->log('timetable.section_shift', $section, $section->label, ['shift' => $old], ['shift' => $shift->label()], user: $user);
                $result['changed']++;
            }

            return $result;
        });
    }

    /** @return array<string, array<int, TimetableEntry>> روز ← period_id ← خانه */
    public function sectionGrid(Section $section): array
    {
        return $this->index(TimetableEntry::query()->with(['subject', 'teacher', 'room'])->where('section_id', $section->id)->get());
    }

    /** @return array<string, array<int, TimetableEntry>> */
    public function teacherGrid(Staff $staff, AcademicYear $year): array
    {
        return $this->index(TimetableEntry::query()->with(['subject', 'section.gradeLevel', 'room'])
            ->where('academic_year_id', $year->id)->where('staff_id', $staff->id)->get());
    }

    /** @return array<string, array<int, TimetableEntry>> */
    public function roomGrid(Room $room, AcademicYear $year): array
    {
        return $this->index(TimetableEntry::query()->with(['subject', 'section.gradeLevel', 'teacher'])
            ->where('academic_year_id', $year->id)->where('room_id', $room->id)->get());
    }

    /** ساعت‌های نوبت‌هایی که در جدول دیده می‌شود (جدول معلم یا اتاق می‌تواند هر دو نوبت را داشته باشد) */
    public function periodsFor(array $grid): Collection
    {
        $ids = collect($grid)->flatMap(fn ($byPeriod) => array_keys($byPeriod))->unique()->all();
        $shifts = Period::whereIn('id', $ids ?: [0])->get()->pluck('shift')->map(fn ($s) => $s->value)->unique()->values()->all();

        return Period::query()->where('is_active', true)
            ->when($shifts !== [], fn ($q) => $q->whereIn('shift', $shifts))
            ->ordered()->get();
    }

    /** معلم پیش‌فرض هر مضمون در این بخش (از «معلمان مضامین») */
    public function defaultTeachers(Section $section): array
    {
        return TeacherAssignment::where('section_id', $section->id)->pluck('staff_id', 'subject_id')->all();
    }

    /** شمار ساعت هر مضمون در هفته */
    public function subjectHours(array $grid): Collection
    {
        return collect($grid)->flatten()->groupBy('subject_id')
            ->map(fn ($e) => ['subject' => $e->first()->subject, 'hours' => $e->count()])
            ->sortByDesc('hours')->values();
    }

    /**
     * ثبت یا تغییر یک خانه.
     *
     * @param  array{subject_id: int, staff_id?: ?int, room_id?: ?int, note?: ?string}  $data
     */
    public function saveCell(Section $section, string $day, Period $period, array $data, User $user): TimetableEntry
    {
        $section->loadMissing('gradeLevel.subjects', 'academicYear');
        if (! in_array($day, $this->days(), true)) {
            throw ValidationException::withMessages(['day' => __('timetable.bad_day')]);
        }
        if ($period->is_break || ! $period->is_active || $period->shift !== $section->shift) {
            throw ValidationException::withMessages(['period_id' => __('timetable.bad_period')]);
        }
        if (! $section->gradeLevel->subjects->contains('id', (int) $data['subject_id'])) {
            throw ValidationException::withMessages(['subject_id' => __('timetable.subject_not_in_grade')]);
        }

        try {
            return DB::transaction(function () use ($section, $day, $period, $data, $user) {
                $slot = fn () => TimetableEntry::query()->with('section.gradeLevel')
                    ->where('academic_year_id', $section->academic_year_id)->where('day', $day)->where('period_id', $period->id)
                    ->where('section_id', '!=', $section->id)->lockForUpdate();

                if (! empty($data['staff_id']) && ($busy = $slot()->where('staff_id', $data['staff_id'])->first())) {
                    throw ValidationException::withMessages(['staff_id' => __('timetable.teacher_busy', ['section' => $busy->section->label])]);
                }
                if (! empty($data['room_id']) && ($busy = $slot()->where('room_id', $data['room_id'])->first())) {
                    throw ValidationException::withMessages(['room_id' => __('timetable.room_busy', ['section' => $busy->section->label])]);
                }

                $entry = TimetableEntry::query()->with(['subject', 'teacher', 'room'])
                    ->where('section_id', $section->id)->where('day', $day)->where('period_id', $period->id)->lockForUpdate()->first()
                    ?? new TimetableEntry;
                $old = $entry->exists ? $this->summary($entry) : [];
                $entry->fill([
                    'subject_id' => (int) $data['subject_id'],
                    'staff_id' => ! empty($data['staff_id']) ? (int) $data['staff_id'] : null,
                    'room_id' => ! empty($data['room_id']) ? (int) $data['room_id'] : null,
                    'note' => ($n = trim((string) ($data['note'] ?? ''))) !== '' ? mb_substr($n, 0, 100) : null,
                ]);
                $entry->forceFill([
                    'academic_year_id' => $section->academic_year_id, 'section_id' => $section->id, 'day' => $day, 'period_id' => $period->id,
                    'updated_by' => $user->id, 'created_by' => $entry->created_by ?? $user->id,
                ])->save();

                $this->logger->log('timetable.saved', $section, $section->label.' — '.__('school.weekdays.'.$day).' — '.$period->name,
                    $old, $this->summary($entry->fresh(['subject', 'teacher', 'room'])), $user);

                return $entry;
            });
        } catch (UniqueConstraintViolationException) {
            // دو کاربر هم‌زمان: کلید یکتای پایگاه داده جلوی تداخل را گرفت
            throw ValidationException::withMessages(['staff_id' => __('timetable.conflict_race')]);
        }
    }

    public function clearCell(Section $section, string $day, Period $period, User $user): void
    {
        DB::transaction(function () use ($section, $day, $period, $user) {
            $entry = TimetableEntry::with(['subject', 'teacher', 'room'])
                ->where('section_id', $section->id)->where('day', $day)->where('period_id', $period->id)->lockForUpdate()->first();
            if (! $entry) {
                return;
            }
            $old = $this->summary($entry);
            $entry->delete();
            $this->logger->log('timetable.cleared', $section, $section->label.' — '.__('school.weekdays.'.$day).' — '.$period->name, $old, user: $user);
        });
    }

    /**
     * کاپی مضامین تقسیم اوقات یک بخش به بخش دیگر همان صنف، نوبت و سال — فقط در خانه‌های خالی مقصد.
     * معلم از «معلمان مضامین» بخش مقصد گذاشته می‌شود اگر همان ساعت آزاد باشد؛ اتاق کاپی نمی‌شود.
     *
     * @return int شمار خانه‌های کاپی‌شده
     */
    public function copySubjects(Section $from, Section $to, User $user): int
    {
        if ($from->id === $to->id || $from->grade_level_id !== $to->grade_level_id || $from->shift !== $to->shift || $from->academic_year_id !== $to->academic_year_id) {
            throw ValidationException::withMessages(['from' => __('timetable.copy_mismatch')]);
        }

        return DB::transaction(function () use ($from, $to, $user) {
            $taken = TimetableEntry::where('section_id', $to->id)->get()->map(fn ($e) => $e->day.'|'.$e->period_id)->all();
            $teachers = $this->defaultTeachers($to);
            $count = 0;
            foreach (TimetableEntry::where('section_id', $from->id)->get() as $e) {
                if (in_array($e->day.'|'.$e->period_id, $taken, true)) {
                    continue;
                }
                $staff = $teachers[$e->subject_id] ?? null;
                $busy = $staff && TimetableEntry::where('academic_year_id', $to->academic_year_id)
                    ->where('day', $e->day)->where('period_id', $e->period_id)->where('staff_id', $staff)->exists();
                $copy = new TimetableEntry(['subject_id' => $e->subject_id, 'staff_id' => $busy ? null : $staff]);
                $copy->forceFill([
                    'academic_year_id' => $to->academic_year_id, 'section_id' => $to->id, 'day' => $e->day, 'period_id' => $e->period_id,
                    'created_by' => $user->id, 'updated_by' => $user->id,
                ])->save();
                $count++;
            }
            $this->logger->log('timetable.copied', $to, $from->label.' ← '.$to->label, new: ['count' => $count], user: $user);

            return $count;
        });
    }

    /** @return array<string, array<int, TimetableEntry>> */
    private function index(Collection $entries): array
    {
        $grid = [];
        foreach ($entries as $e) {
            $grid[$e->day][$e->period_id] = $e;
        }

        return $grid;
    }

    private function summary(TimetableEntry $e): array
    {
        return [
            'subject' => (string) $e->subject?->name,
            'teacher' => (string) $e->teacher?->full_name,
            'room' => (string) $e->room?->name,
            'note' => (string) $e->note,
        ];
    }
}
