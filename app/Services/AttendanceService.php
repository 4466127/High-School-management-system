<?php

namespace App\Services;

use App\Enums\AttendanceStatus;
use App\Enums\EmploymentStatus;
use App\Enums\EnrollmentStatus;
use App\Models\AcademicYear;
use App\Models\AttendanceRecord;
use App\Models\AttendanceSession;
use App\Models\Enrollment;
use App\Models\Section;
use App\Models\Staff;
use App\Models\StaffAttendance;
use App\Models\User;
use App\Support\Digits;
use App\Support\JalaliDate;
use Carbon\CarbonImmutable;
use DateTimeInterface;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

/**
 * حاضری روزانه‌ی بخش‌ها و کارکنان، و شمارش‌های گزارش‌ها.
 * قاعده‌ها: یک ورق برای هر بخش در هر روز (کلید یکتا)؛ هیچ سطری حذف نمی‌شود؛
 * هر تغییر سطر موجود با مقدار قبلی و جدید در activity_logs ثبت می‌شود.
 */
class AttendanceService
{
    public function __construct(private readonly ActivityLogger $logger) {}

    public static function today(): CarbonImmutable
    {
        return CarbonImmutable::now(JalaliDate::TIMEZONE)->startOfDay();
    }

    /** بخش‌هایی که کاربر می‌بیند: null = همه (مدیر، اداره)؛ معلم = بخش‌های خودش در سال جاری */
    public function scopedSectionIds(User $user): ?array
    {
        return $user->hasPermission('attendance.edit') || $user->hasPermission('academics.manage')
            ? null
            : $user->taughtSectionIds();
    }

    /** بخش‌های یک سال در محدوده‌ی کاربر، به ترتیب صنف */
    public function sections(User $user, AcademicYear $year): Collection
    {
        $ids = $this->scopedSectionIds($user);

        return Section::query()
            ->with(['gradeLevel', 'classTeacher'])
            ->withCount('activeEnrollments')
            ->where('academic_year_id', $year->id)
            ->when($ids !== null, fn ($q) => $q->whereIn('sections.id', $ids ?: [0]))
            ->ordered()
            ->get();
    }

    public function findSession(Section $section, DateTimeInterface $date): ?AttendanceSession
    {
        return AttendanceSession::query()
            ->where('section_id', $section->id)
            ->where('date', $date->format('Y-m-d'))
            ->first();
    }

    /**
     * دانش‌آموزان یک ورق: کسانی که در آن روز شامل این بخش بودند، به‌علاوه‌ی هر کسی که در همین ورق سطر دارد.
     * کسی که پیش از آن روز منتقل شده یا ترک کرده، یا بعد از آن روز شامل شده، نمی‌آید.
     *
     * @return Collection<int, Enrollment> با کلید enrollment_id
     */
    public function sheetEnrollments(Section $section, DateTimeInterface $date, ?AttendanceSession $session = null): Collection
    {
        $day = $date->format('Y-m-d');
        $recorded = $session ? $session->records()->pluck('enrollment_id')->all() : [];

        return Enrollment::query()
            ->with('student')
            ->where(function (Builder $q) use ($section, $day, $recorded) {
                $q->where(function (Builder $q) use ($section, $day) {
                    $q->where('section_id', $section->id)
                        ->where(fn (Builder $q) => $q->whereNull('enrolled_on')->orWhere('enrolled_on', '<=', $day))
                        ->where(fn (Builder $q) => $q
                            ->whereNotIn('status', [EnrollmentStatus::Transferred, EnrollmentStatus::Dropped])
                            ->orWhere('left_on', '>', $day));
                })->orWhereIn('id', $recorded ?: [0]);
            })
            ->orderByRaw('roll_no IS NULL, roll_no')
            ->orderBy('id')
            ->get()
            ->keyBy('id');
    }

    /**
     * ذخیره‌ی ورق حاضری یک بخش (ساخت یا اصلاح).
     *
     * @param  array<int|string, string>  $statuses  enrollment_id => present|late|absent|excused|sick
     * @param  array<int|string, ?string>  $notes  enrollment_id => یادداشت
     * @return array{created: bool, changed: int, counts: array<string, int>}
     */
    public function saveSection(Section $section, DateTimeInterface $date, array $statuses, array $notes, User $user): array
    {
        $day = $date->format('Y-m-d');

        return DB::transaction(function () use ($section, $date, $day, $statuses, $notes, $user) {
            $session = AttendanceSession::createOrFirst(
                ['section_id' => $section->id, 'date' => $day],
                ['taken_by' => $user->id, 'taken_at' => now()],
            );
            $created = $session->wasRecentlyCreated;
            $session = AttendanceSession::whereKey($session->id)->lockForUpdate()->first();

            $records = $session->records()->get()->keyBy('enrollment_id');
            $enrollments = $this->sheetEnrollments($section, $date, $session);

            $missing = $enrollments->keys()->reject(fn ($id) => isset($statuses[$id]));
            if ($missing->isNotEmpty()) {
                throw ValidationException::withMessages(['status' => __('attendance.list_changed')]);
            }

            $old = [];
            $new = [];
            $counts = array_fill_keys(array_column(AttendanceStatus::cases(), 'value'), 0);

            foreach ($enrollments as $id => $enrollment) {
                $status = AttendanceStatus::from($statuses[$id]);
                $note = trim((string) ($notes[$id] ?? '')) ?: null;
                $counts[$status->value]++;
                $record = $records->get($id);
                $who = $this->studentKey($enrollment);

                if (! $record) {
                    AttendanceRecord::create([
                        'attendance_session_id' => $session->id,
                        'enrollment_id' => $id,
                        'status' => $status,
                        'note' => $note,
                        'updated_by' => $user->id,
                    ]);
                    if (! $created) {
                        $old[$who] = '—';
                        $new[$who] = $this->describe($status, $note);
                    }

                    continue;
                }

                if ($record->status !== $status || $record->note !== $note) {
                    $old[$who] = $this->describe($record->status, $record->note);
                    $new[$who] = $this->describe($status, $note);
                    $record->update(['status' => $status, 'note' => $note, 'updated_by' => $user->id]);
                }
            }

            $section->loadMissing('gradeLevel');
            $description = $section->label.' — '.JalaliDate::format($date);

            if ($created) {
                $this->logger->log('attendance.taken', $session, $description, new: $this->labelCounts($counts), user: $user);
            } elseif ($new !== []) {
                $session->update(['updated_by' => $user->id]);
                $this->logger->log('attendance.corrected', $session, $description, $old, $new, $user);
            }

            return ['created' => $created, 'changed' => count($new), 'counts' => $counts];
        });
    }

    // ------------------------------------------------------------------ کارکنان

    /** کارکنان ورق یک روز: کارکنان برسر وظیفه و رخصت، به‌علاوه‌ی هر کسی که در آن روز سطر دارد */
    public function staffForDay(DateTimeInterface $date): Collection
    {
        $recorded = StaffAttendance::where('date', $date->format('Y-m-d'))->pluck('staff_id')->all();

        return Staff::query()
            ->withTrashed()
            ->where(fn (Builder $q) => $q
                ->where(fn (Builder $q) => $q->whereNull('deleted_at')->where('employment_status', '!=', EmploymentStatus::Left))
                ->orWhereIn('id', $recorded ?: [0]))
            ->orderBy('staff_type')
            ->orderBy('first_name')
            ->orderBy('last_name')
            ->get()
            ->keyBy('id');
    }

    /** @return Collection<int, StaffAttendance> با کلید staff_id */
    public function staffRecords(DateTimeInterface $date): Collection
    {
        return StaffAttendance::where('date', $date->format('Y-m-d'))->get()->keyBy('staff_id');
    }

    /**
     * @param  array<int|string, string>  $statuses  staff_id => وضعیت
     * @return array{created: bool, changed: int, counts: array<string, int>}
     */
    public function saveStaff(DateTimeInterface $date, array $statuses, array $notes, User $user): array
    {
        $day = $date->format('Y-m-d');

        return DB::transaction(function () use ($date, $day, $statuses, $notes, $user) {
            $staff = $this->staffForDay($date);
            $records = StaffAttendance::where('date', $day)->lockForUpdate()->get()->keyBy('staff_id');
            $created = $records->isEmpty();

            if ($staff->keys()->reject(fn ($id) => isset($statuses[$id]))->isNotEmpty()) {
                throw ValidationException::withMessages(['status' => __('attendance.list_changed')]);
            }

            $old = [];
            $new = [];
            $counts = array_fill_keys(array_column(AttendanceStatus::cases(), 'value'), 0);

            foreach ($staff as $id => $member) {
                $status = AttendanceStatus::from($statuses[$id]);
                $note = trim((string) ($notes[$id] ?? '')) ?: null;
                $counts[$status->value]++;
                $record = $records->get($id);

                if (! $record) {
                    StaffAttendance::create(['staff_id' => $id, 'date' => $day, 'status' => $status, 'note' => $note, 'recorded_by' => $user->id]);
                    if (! $created) {
                        $old[$member->full_name] = '—';
                        $new[$member->full_name] = $this->describe($status, $note);
                    }

                    continue;
                }

                if ($record->status !== $status || $record->note !== $note) {
                    $old[$member->full_name] = $this->describe($record->status, $record->note);
                    $new[$member->full_name] = $this->describe($status, $note);
                    $record->update(['status' => $status, 'note' => $note, 'recorded_by' => $user->id]);
                }
            }

            $description = __('attendance.staff_title').' — '.JalaliDate::format($date);
            if ($created) {
                $this->logger->log('attendance.staff_taken', null, $description, new: $this->labelCounts($counts), user: $user);
            } elseif ($new !== []) {
                $this->logger->log('attendance.staff_corrected', null, $description, $old, $new, $user);
            }

            return ['created' => $created, 'changed' => count($new), 'counts' => $counts];
        });
    }

    // ------------------------------------------------------------------ شمارش‌ها

    /**
     * شمار وضعیت‌ها برای هر بخش در یک روز.
     *
     * @param  list<int>  $sectionIds
     * @return array<int, array<string, int>> section_id => [status => تعداد]
     */
    public function dayCounts(array $sectionIds, DateTimeInterface $date): array
    {
        $rows = AttendanceRecord::query()
            ->join('attendance_sessions', 'attendance_sessions.id', '=', 'attendance_records.attendance_session_id')
            ->where('attendance_sessions.date', $date->format('Y-m-d'))
            ->whereIn('attendance_sessions.section_id', $sectionIds ?: [0])
            ->groupBy('attendance_sessions.section_id', 'attendance_records.status')
            ->selectRaw('attendance_sessions.section_id as section_id, attendance_records.status as status, COUNT(*) as total')
            ->get();

        $result = [];
        foreach ($rows as $row) {
            $result[$row->section_id][$row->getRawOriginal('status')] = (int) $row->total;
        }

        return $result;
    }

    /** غیرحاضر، رخصت، مریض و تأخیر یک روز (برای گزارش روزانه) */
    public function dayNonPresent(array $sectionIds, DateTimeInterface $date): Collection
    {
        return AttendanceRecord::query()
            ->with(['enrollment.student', 'session.section.gradeLevel'])
            ->whereHas('session', fn ($q) => $q->where('date', $date->format('Y-m-d'))->whereIn('section_id', $sectionIds ?: [0]))
            ->where('status', '!=', AttendanceStatus::Present)
            ->get()
            ->sortBy(fn (AttendanceRecord $r) => [$r->session->section->gradeLevel->sort_order, $r->session->section->name, $r->enrollment->roll_no ?? 9999])
            ->values();
    }

    /**
     * جدول ماهوار یک بخش.
     *
     * @return array{days: list<CarbonImmutable>, rows: Collection, sessions: Collection}
     *                                                                                    rows: هر سطر ['enrollment' => Enrollment, 'marks' => [Y-m-d => AttendanceStatus], 'totals' => [status => n]]
     */
    public function monthGrid(Section $section, CarbonImmutable $from, CarbonImmutable $to): array
    {
        $days = [];
        for ($d = $from; $d->lte($to); $d = $d->addDay()) {
            $days[] = $d;
        }

        $sessions = AttendanceSession::query()
            ->where('section_id', $section->id)
            ->whereBetween('date', [$from->format('Y-m-d'), $to->format('Y-m-d')])
            ->get()
            ->keyBy(fn (AttendanceSession $s) => $s->date->format('Y-m-d'));

        $records = AttendanceRecord::query()
            ->whereIn('attendance_session_id', $sessions->pluck('id')->all() ?: [0])
            ->get();
        $dateOf = $sessions->mapWithKeys(fn (AttendanceSession $s) => [$s->id => $s->date->format('Y-m-d')]);

        $enrollments = Enrollment::query()
            ->with('student')
            ->where(fn (Builder $q) => $q
                ->where(fn (Builder $q) => $q->where('section_id', $section->id)->where('status', EnrollmentStatus::Active))
                ->orWhereIn('id', $records->pluck('enrollment_id')->unique()->all() ?: [0]))
            ->orderByRaw('roll_no IS NULL, roll_no')
            ->orderBy('id')
            ->get();

        $byEnrollment = $records->groupBy('enrollment_id');
        $rows = $enrollments->map(function (Enrollment $enrollment) use ($byEnrollment, $dateOf) {
            $marks = [];
            $totals = array_fill_keys(array_column(AttendanceStatus::cases(), 'value'), 0);
            foreach ($byEnrollment->get($enrollment->id, collect()) as $record) {
                $marks[$dateOf[$record->attendance_session_id]] = $record->status;
                $totals[$record->status->value]++;
            }

            return ['enrollment' => $enrollment, 'marks' => $marks, 'totals' => $totals];
        });

        return ['days' => $days, 'rows' => $rows, 'sessions' => $sessions];
    }

    /**
     * خلاصه‌ی صنف‌ها در یک بازه: تعداد روزهای ثبت‌شده و شمار هر وضعیت.
     *
     * @param  list<int>  $sectionIds
     * @return array<int, array{days: int, counts: array<string, int>}>
     */
    public function sectionSummary(array $sectionIds, CarbonImmutable $from, CarbonImmutable $to): array
    {
        $range = [$from->format('Y-m-d'), $to->format('Y-m-d')];
        $days = AttendanceSession::query()
            ->whereIn('section_id', $sectionIds ?: [0])
            ->whereBetween('date', $range)
            ->groupBy('section_id')
            ->selectRaw('section_id, COUNT(*) as total')
            ->pluck('total', 'section_id');

        $result = [];
        foreach ($sectionIds as $id) {
            $result[$id] = ['days' => (int) ($days[$id] ?? 0), 'counts' => array_fill_keys(array_column(AttendanceStatus::cases(), 'value'), 0)];
        }

        AttendanceRecord::query()
            ->join('attendance_sessions', 'attendance_sessions.id', '=', 'attendance_records.attendance_session_id')
            ->whereIn('attendance_sessions.section_id', $sectionIds ?: [0])
            ->whereBetween('attendance_sessions.date', $range)
            ->groupBy('attendance_sessions.section_id', 'attendance_records.status')
            ->selectRaw('attendance_sessions.section_id as section_id, attendance_records.status as status, COUNT(*) as total')
            ->get()
            ->each(function ($row) use (&$result) {
                $result[$row->section_id]['counts'][$row->getRawOriginal('status')] = (int) $row->total;
            });

        return $result;
    }

    /**
     * شمار وضعیت‌های هر شمولیت در یک سال (اختیاری: فقط یک بخش، یا فقط حد اقل غیرحاضری).
     *
     * @param  list<int>|null  $sectionIds  null = همه
     * @return Collection<int, object{enrollment_id:int, present:int, late:int, absent:int, excused:int, sick:int, total:int}>
     */
    public function yearTotals(AcademicYear $year, ?array $sectionIds = null, int $minAbsent = 0): Collection
    {
        $sums = collect(AttendanceStatus::cases())
            ->map(fn (AttendanceStatus $s) => "SUM(attendance_records.status = '{$s->value}') as {$s->value}")
            ->join(', ');

        return AttendanceRecord::query()
            ->join('enrollments', 'enrollments.id', '=', 'attendance_records.enrollment_id')
            ->where('enrollments.academic_year_id', $year->id)
            ->when($sectionIds !== null, fn ($q) => $q->whereIn('enrollments.section_id', $sectionIds ?: [0]))
            ->groupBy('attendance_records.enrollment_id')
            ->selectRaw("attendance_records.enrollment_id as enrollment_id, {$sums}, COUNT(*) as total")
            ->when($minAbsent > 0, fn ($q) => $q->havingRaw("SUM(attendance_records.status = 'absent') >= ?", [$minAbsent]))
            ->toBase()
            ->get()
            ->map(function ($row) {
                foreach ($row as $key => $value) {
                    $row->{$key} = (int) $value;
                }

                return $row;
            })
            ->keyBy('enrollment_id');
    }

    /** حاضری یک شمولیت: شمار وضعیت‌ها و فهرست روزهای غیر «حاضر» (برای پرونده‌ی دانش‌آموز) */
    public function enrollmentHistory(Enrollment $enrollment): array
    {
        $records = AttendanceRecord::query()
            ->with('session.section.gradeLevel')
            ->join('attendance_sessions', 'attendance_sessions.id', '=', 'attendance_records.attendance_session_id')
            ->where('attendance_records.enrollment_id', $enrollment->id)
            ->orderByDesc('attendance_sessions.date')
            ->select('attendance_records.*')
            ->get();

        $totals = array_fill_keys(array_column(AttendanceStatus::cases(), 'value'), 0);
        foreach ($records as $record) {
            $totals[$record->status->value]++;
        }

        return [
            'totals' => $totals,
            'days' => $records->count(),
            'notPresent' => $records->reject(fn (AttendanceRecord $r) => $r->status === AttendanceStatus::Present)->values(),
        ];
    }

    /** شمار حاضری کارکنان در یک بازه: staff_id => [status => n] */
    public function staffTotals(CarbonImmutable $from, CarbonImmutable $to): array
    {
        $result = [];
        StaffAttendance::query()
            ->whereBetween('date', [$from->format('Y-m-d'), $to->format('Y-m-d')])
            ->groupBy('staff_id', 'status')
            ->selectRaw('staff_id, status, COUNT(*) as total')
            ->get()
            ->each(function ($row) use (&$result) {
                $result[$row->staff_id][$row->getRawOriginal('status')] = (int) $row->total;
            });

        return $result;
    }

    /** فیصدی حاضری (حاضر + تأخیر) از کل روزهای ثبت‌شده */
    public static function percent(array $counts): ?float
    {
        $total = array_sum($counts);

        return $total > 0 ? round((($counts['present'] ?? 0) + ($counts['late'] ?? 0)) * 100 / $total, 1) : null;
    }

    private function studentKey(Enrollment $enrollment): string
    {
        return Digits::toPersian((string) $enrollment->roll_no).' — '.$enrollment->student->full_name;
    }

    private function describe(AttendanceStatus $status, ?string $note): string
    {
        return $status->label().($note ? ' ('.$note.')' : '');
    }

    /** @return array<string, int> برچسب فارسی => تعداد (فقط غیرصفرها) */
    private function labelCounts(array $counts): array
    {
        $result = [];
        foreach ($counts as $status => $count) {
            if ($count > 0) {
                $result[AttendanceStatus::from($status)->label()] = $count;
            }
        }

        return $result;
    }
}
