<?php

namespace App\Services;

use App\Enums\EnrollmentStatus;
use App\Enums\FinalResult;
use App\Enums\TransactionType;
use App\Models\AcademicYear;
use App\Models\CourseLedgerEntry;
use App\Models\Enrollment;
use App\Models\Payment;
use App\Models\SchoolTransaction;
use App\Models\Section;
use App\Support\Money;
use Carbon\CarbonImmutable;
use Illuminate\Support\Collection;

/**
 * گزارش‌های مرحله‌ی ۹ (فقط خواندن). محدوده‌ی بخش‌ها را کنترلر از ResultService::visibleSections می‌دهد.
 * پول همه به سنت (int)؛ پول کورس‌ها همیشه جدا از پول مکتب.
 */
class ReportService
{
    public function __construct(
        private readonly AttendanceService $attendance,
        private readonly ResultService $results,
        private readonly FinanceService $finance,
    ) {}

    /**
     * شمار دانش‌آموزان شامل هر بخش به جنسیت.
     *
     * @return Collection<int, array{section: Section, male: int, female: int, total: int}>
     */
    public function studentStats(Collection $sections): Collection
    {
        $counts = Enrollment::query()
            ->join('students', 'students.id', '=', 'enrollments.student_id')
            ->whereIn('enrollments.section_id', $sections->pluck('id')->all() ?: [0])
            ->where('enrollments.status', EnrollmentStatus::Active)
            ->whereNull('students.deleted_at')
            ->groupBy('enrollments.section_id', 'students.gender')
            ->selectRaw('enrollments.section_id as section_id, students.gender as gender, COUNT(*) as total')
            ->get()
            ->groupBy('section_id');

        return $sections->map(function (Section $section) use ($counts) {
            $rows = $counts->get($section->id, collect())->pluck('total', 'gender');
            $male = (int) ($rows['male'] ?? 0);
            $female = (int) ($rows['female'] ?? 0);

            return ['section' => $section, 'male' => $male, 'female' => $female, 'total' => $male + $female];
        })->values();
    }

    /** فهرست دانش‌آموزان شامل (برای چاپ و CSV) */
    public function studentList(Collection $sections, ?string $gender = null): Collection
    {
        return Enrollment::query()
            ->with(['student', 'section.gradeLevel'])
            ->whereIn('section_id', $sections->pluck('id')->all() ?: [0])
            ->where('status', EnrollmentStatus::Active)
            ->whereHas('student', fn ($q) => $q->when($gender, fn ($q) => $q->where('gender', $gender)))
            ->get()
            ->sortBy(fn (Enrollment $e) => [$e->section->gradeLevel->sort_order, $e->section->name, $e->roll_no ?? 9999])
            ->values();
    }

    /**
     * حاضری هر بخش در یک بازه.
     *
     * @return Collection<int, array{section: Section, days: int, counts: array<string, int>, percent: ?float}>
     */
    public function attendanceSummary(Collection $sections, CarbonImmutable $from, CarbonImmutable $to): Collection
    {
        $summary = $this->attendance->sectionSummary($sections->pluck('id')->all(), $from, $to);

        return $sections->map(fn (Section $s) => [
            'section' => $s,
            'days' => $summary[$s->id]['days'] ?? 0,
            'counts' => $summary[$s->id]['counts'] ?? [],
            'percent' => AttendanceService::percent($summary[$s->id]['counts'] ?? []),
        ])->values();
    }

    /**
     * نتیجه‌ی هر بخش (نهایی‌شده یا موقت) و فهرست ناکام، مشروط و محروم.
     *
     * @return array{sections: Collection, students: Collection}
     */
    public function resultsSummary(Collection $sections): array
    {
        $rows = collect();
        $students = collect();

        foreach ($sections as $section) {
            $cards = $this->results->reportCards($section);
            $counts = array_fill_keys(array_column(FinalResult::cases(), 'value'), 0) + ['incomplete' => 0];
            foreach ($cards as $card) {
                $counts[$card['result'] ?? 'incomplete']++;
                if (in_array($card['result'], [FinalResult::Failed->value, FinalResult::Conditional->value, FinalResult::Deprived->value], true)) {
                    $students->push([
                        'section' => $section,
                        'enrollment' => $card['enrollment'],
                        'result' => FinalResult::from($card['result']),
                        'average' => $card['average'],
                        'failed_subjects' => collect($card['subjects'])->where('passed', false)->pluck('name')->all(),
                        'provisional' => $card['provisional'],
                    ]);
                }
            }
            $decided = $cards->count() - $counts['incomplete'];
            $rows->push([
                'section' => $section,
                'total' => $cards->count(),
                'counts' => $counts,
                'pass_rate' => $decided > 0 ? round($counts[FinalResult::Passed->value] * 100 / $decided, 1) : null,
                'finalized' => $this->results->isFinalized($section),
            ]);
        }

        return ['sections' => $rows, 'students' => $students];
    }

    /**
     * خلاصه‌ی مالی یک بازه: مکتب (فیس، درآمد، مصرف) و کورس‌ها (جدا).
     *
     * @return array{school: ?array, courses: ?array}
     */
    public function financeSummary(string $from, string $to, bool $school, bool $courses, ?AcademicYear $year): array
    {
        $result = ['school' => null, 'courses' => null];

        if ($school) {
            $sum = fn (TransactionType $t) => Money::toCents(SchoolTransaction::whereNull('voided_at')->where('type', $t)->whereBetween('date', [$from, $to])->sum('amount'));
            $fees = Money::toCents(Payment::whereNull('voided_at')->whereBetween('paid_on', [$from, $to])->sum('amount'));
            $income = $sum(TransactionType::Income);
            $expense = $sum(TransactionType::Expense);
            $debtors = $year ? $this->finance->debtors($year) : collect();
            $result['school'] = [
                'fees' => $fees, 'income' => $income, 'expense' => $expense, 'balance' => $fees + $income - $expense,
                'debt' => (int) $debtors->sum('remaining'), 'debtors' => $debtors->count(),
            ];
        }

        if ($courses) {
            $entries = CourseLedgerEntry::query()->whereNull('voided_at')->whereBetween('date', [$from, $to])->get(['entry_type', 'channel', 'staff_id', 'amount']);
            $period = CourseLedgerCalculator::summarize($entries->map(fn ($e) => ['type' => $e->entry_type, 'channel' => $e->channel, 'staff_id' => $e->staff_id, 'cents' => Money::toCents($e->amount)]));
            $all = CourseLedgerEntry::query()->whereNull('voided_at')->get(['entry_type', 'channel', 'amount']);
            $result['courses'] = $period + [
                'cash_balance' => (int) $all->sum(fn ($e) => CourseLedgerCalculator::cashEffect($e->entry_type, $e->channel, Money::toCents($e->amount))),
            ];
        }

        return $result;
    }
}
