<?php

namespace App\Services;

use App\Enums\CourseStatus;
use App\Enums\EmploymentStatus;
use App\Enums\ExamStatus;
use App\Enums\ParticipantStatus;
use App\Models\AcademicYear;
use App\Models\Course;
use App\Models\CourseGroup;
use App\Models\CourseLedgerEntry;
use App\Models\CourseParticipant;
use App\Models\CourseSession;
use App\Models\Exam;
use App\Models\Payment;
use App\Models\SchoolTransaction;
use App\Models\Setting;
use App\Models\Staff;
use App\Models\User;
use App\Support\Digits;
use App\Support\JalaliDate;
use App\Support\Money;
use Carbon\CarbonImmutable;

/**
 * کارت‌های داشبورد هر کاربر به اندازه‌ی مجوزهایش. هر بخش null یعنی برای این کاربر نشان داده نمی‌شود.
 */
class DashboardService
{
    public function __construct(
        private readonly AttendanceService $attendance,
        private readonly ResultService $results,
        private readonly ReportService $reports,
        private readonly FinanceService $finance,
        private readonly BackupService $backups,
        private readonly LicenseService $license,
    ) {}

    public function for(User $user): array
    {
        $year = AcademicYear::current();
        $today = AttendanceService::today();

        return [
            'year' => $year,
            'today' => $today,
            'students' => $this->students($user, $year),
            'attendance' => $this->attendanceToday($user, $year, $today),
            'exams' => $this->exams($user, $year, $today),
            'finance' => $this->financeCard($user, $year, $today),
            'courses' => $this->courses($user, $today),
            'backup' => $this->backup($user),
            'license' => $this->licenseWarning($user),
        ];
    }

    /** هشدار لایسنس فقط برای مدیر سیستم و فقط وقتی لایسنس تنظیم شده ولی معتبر نیست؛ هیچ چیزی بسته نمی‌شود */
    private function licenseWarning(User $user): ?string
    {
        if (! $user->hasPermission('license.manage') || ! $this->license->needsAttention()) {
            return null;
        }

        return $this->license->status()['state'];
    }

    /** یادآوری پشتیبان برای مدیر سیستم، فقط وقتی پشتیبان ماهانه کهنه یا هیچ است */
    private function backup(User $user): ?array
    {
        if (! $user->hasPermission('backups.manage') || ! $this->backups->isDue()) {
            return null;
        }
        $latest = $this->backups->latest();

        return ['days' => $latest ? (int) $latest['time']->diffInDays(CarbonImmutable::now(JalaliDate::TIMEZONE)) : null];
    }

    private function students(User $user, ?AcademicYear $year): ?array
    {
        if (! $year || ! ($user->hasPermission('students.view_all') || $user->hasPermission('academics.manage') || $user->hasPermission('students.view'))) {
            return null;
        }
        $sections = $this->results->visibleSections($user, $year);
        if ($sections->isEmpty()) {
            return null;
        }
        $stats = $this->reports->studentStats($sections);

        return [
            'total' => $stats->sum('total'),
            'male' => $stats->sum('male'),
            'female' => $stats->sum('female'),
            'sections' => $sections->count(),
            'staff' => $user->hasPermission('staff.manage') ? Staff::where('employment_status', '!=', EmploymentStatus::Left)->count() : null,
            'mine' => ! ($user->hasPermission('students.view_all') || $user->hasPermission('exams.manage')),
        ];
    }

    private function attendanceToday(User $user, ?AcademicYear $year, $today): ?array
    {
        if (! $year || ! ($user->hasPermission('attendance.take') || $user->hasPermission('attendance.edit') || $user->hasPermission('reports.academic'))) {
            return null;
        }
        $sections = $this->attendance->sections($user, $year);
        if ($sections->isEmpty()) {
            return null;
        }
        $counts = $this->attendance->dayCounts($sections->pluck('id')->all(), $today);
        $totals = [];
        foreach ($counts as $bySection) {
            foreach ($bySection as $status => $n) {
                $totals[$status] = ($totals[$status] ?? 0) + $n;
            }
        }
        $minAbsent = (int) Setting::get('absence_alert_days', 10);

        return [
            'sections' => $sections->map(fn ($s) => ['section' => $s, 'taken' => isset($counts[$s->id]), 'absent' => $counts[$s->id]['absent'] ?? 0]),
            'taken' => count($counts),
            'total' => $sections->count(),
            'counts' => $totals,
            'percent' => AttendanceService::percent($totals),
            'high_absence' => $user->hasPermission('reports.academic') || $user->hasPermission('attendance.edit')
                ? $this->attendance->yearTotals($year, $this->attendance->scopedSectionIds($user), $minAbsent)->count()
                : null,
            'min_absent' => $minAbsent,
        ];
    }

    private function exams(User $user, ?AcademicYear $year, $today): ?array
    {
        if (! $year || ! ($user->hasPermission('exams.manage') || $user->hasPermission('marks.enter'))) {
            return null;
        }

        return [
            'open' => Exam::where('academic_year_id', $year->id)->where('status', ExamStatus::Open)->ordered()->get(),
            'upcoming' => Exam::where('academic_year_id', $year->id)->where('status', ExamStatus::Draft)
                ->whereNotNull('start_date')->where('start_date', '>=', $today->toDateString())->orderBy('start_date')->limit(3)->get(),
        ];
    }

    private function financeCard(User $user, ?AcademicYear $year, $today): ?array
    {
        if (! $user->hasPermission('finance.view')) {
            return null;
        }
        $fees = Setting::enabled('fees_enabled');
        $ledger = Setting::enabled('school_ledger_enabled');
        if (! $fees && ! $ledger) {
            return null;
        }
        [$jy, $jm] = JalaliDate::fromGregorian($today);
        [$from, $to] = JalaliDate::monthRange($jy, $jm);
        $range = [$from->toDateString(), $to->toDateString()];
        $debtors = $fees && $year ? $this->finance->debtors($year) : collect();

        return [
            'fees' => $fees,
            'ledger' => $ledger,
            'today_received' => $fees ? Money::toCents(Payment::whereNull('voided_at')->where('paid_on', $today->toDateString())->sum('amount')) : 0,
            'month_received' => $fees ? Money::toCents(Payment::whereNull('voided_at')->whereBetween('paid_on', $range)->sum('amount')) : 0,
            'debt' => (int) $debtors->sum('remaining'),
            'debtors' => $debtors->count(),
            'month_income' => $ledger ? Money::toCents(SchoolTransaction::whereNull('voided_at')->where('type', 'income')->whereBetween('date', $range)->sum('amount')) : 0,
            'month_expense' => $ledger ? Money::toCents(SchoolTransaction::whereNull('voided_at')->where('type', 'expense')->whereBetween('date', $range)->sum('amount')) : 0,
            'month_label' => JalaliDate::monthName($jm).' '.Digits::toPersian((string) $jy),
        ];
    }

    private function courses(User $user, $today): ?array
    {
        $manage = $user->hasPermission('courses.manage');
        $ledger = $user->hasPermission('courses.ledger');
        if (! $manage && ! $ledger && ! $user->hasPermission('courses.attendance')) {
            return null;
        }

        $groups = CourseGroup::query()
            ->with('course')
            ->withCount('activeParticipants')
            ->where('is_active', true)
            ->whereHas('course', fn ($q) => $q->where('status', CourseStatus::Active))
            ->when(! $manage && ! $ledger, fn ($q) => $q->where('staff_id', $user->staff_id ?? 0))
            ->get();
        if ($groups->isEmpty() && ! $manage && ! $ledger) {
            return null;
        }
        $taken = CourseSession::whereIn('course_group_id', $groups->pluck('id')->all() ?: [0])->where('date', $today->toDateString())->pluck('course_group_id')->all();

        return [
            'active' => $groups->pluck('course_id')->unique()->count(),
            'planned' => $manage || $ledger ? Course::where('status', CourseStatus::Planned)->count() : null,
            'participants' => CourseParticipant::whereIn('course_group_id', $groups->pluck('id')->all() ?: [0])->where('status', ParticipantStatus::Active)->count(),
            'groups' => $groups,
            'taken' => $taken,
            'cash_balance' => $ledger
                ? (int) CourseLedgerEntry::whereNull('voided_at')->get(['entry_type', 'channel', 'amount'])
                    ->sum(fn ($e) => CourseLedgerCalculator::cashEffect($e->entry_type, $e->channel, Money::toCents($e->amount)))
                : null,
            'can_attendance' => $manage || $user->hasPermission('courses.attendance'),
        ];
    }
}
