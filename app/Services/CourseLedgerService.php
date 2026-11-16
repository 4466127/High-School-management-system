<?php

namespace App\Services;

use App\Enums\CourseChannel;
use App\Enums\CourseEntryType;
use App\Models\Course;
use App\Models\CourseLedgerEntry;
use App\Models\User;
use App\Support\Money;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

/**
 * دفتر مالی جدای هر کورس. پول کورس با فیس یا دفتر مکتب جمع نمی‌شود.
 * معاش و مصرف از صندوق بیشتر از مانده پذیرفته نمی‌شود؛ باطل کردن کمکِ صندوق هم مانده را منفی نمی‌کند.
 */
class CourseLedgerService
{
    public function __construct(private readonly ActivityLogger $logger) {}

    /** @param  array{entry_type: CourseEntryType, channel: CourseChannel, staff_id: ?int, amount_cents: int, date: string, donor_name: ?string, description: ?string}  $data */
    public function record(Course $course, array $data, User $user): CourseLedgerEntry
    {
        return DB::transaction(function () use ($course, $data, $user) {
            Course::whereKey($course->id)->lockForUpdate()->first();
            $effect = CourseLedgerCalculator::cashEffect($data['entry_type'], $data['channel'], $data['amount_cents']);
            if ($effect < 0 && $this->balance($course) + $effect < 0) {
                throw ValidationException::withMessages(['amount' => __('courses.balance_insufficient', ['balance' => Money::format(Money::fromCents($this->balance($course)))])]);
            }

            $entry = CourseLedgerEntry::create([
                'course_id' => $course->id,
                'entry_type' => $data['entry_type'],
                'channel' => $data['channel'],
                'staff_id' => $data['staff_id'],
                'amount' => Money::fromCents($data['amount_cents']),
                'date' => $data['date'],
                'donor_name' => $data['donor_name'],
                'description' => $data['description'],
                'recorded_by' => $user->id,
            ]);
            $entry->load('teacher');
            $this->logger->log('course.ledger_recorded', $course, $course->name.' — '.$entry->entry_type->label(), new: [
                'amount' => Money::plain($entry->amount), 'channel' => $entry->channel->label(),
                'party' => $entry->donor_name ?? $entry->teacher?->full_name,
            ], user: $user);

            return $entry;
        });
    }

    public function void(CourseLedgerEntry $entry, string $reason, User $user): void
    {
        DB::transaction(function () use ($entry, $reason, $user) {
            $course = Course::whereKey($entry->course_id)->lockForUpdate()->first();
            $entry = CourseLedgerEntry::whereKey($entry->id)->lockForUpdate()->first();
            if ($entry->isVoided()) {
                return;
            }
            $effect = CourseLedgerCalculator::cashEffect($entry->entry_type, $entry->channel, Money::toCents($entry->amount));
            if ($effect > 0 && $this->balance($course) - $effect < 0) {
                throw ValidationException::withMessages(['reason' => __('courses.void_makes_negative')]);
            }

            $entry->forceFill(['voided_at' => now(), 'voided_by' => $user->id, 'void_reason' => $reason])->save();
            $this->logger->log('course.ledger_voided', $course, $course->name.' — '.$entry->entry_type->label().' — '.$reason, ['amount' => Money::plain($entry->amount)], ['void_reason' => $reason], $user);
        });
    }

    /** جمع‌ها (سطرهای باطل‌نشده) */
    public function summary(Course $course): array
    {
        return CourseLedgerCalculator::summarize($this->activeEntries($course->id)->map(fn (CourseLedgerEntry $e) => [
            'type' => $e->entry_type, 'channel' => $e->channel, 'staff_id' => $e->staff_id, 'cents' => Money::toCents($e->amount),
        ]));
    }

    public function balance(Course $course): int
    {
        return $this->summary($course)['balance'];
    }

    /**
     * جمع‌های همه‌ی کورس‌ها یک‌جا (برای فهرست کورس‌ها و صفحه‌ی مالی کورس‌ها).
     *
     * @return Collection<int, array> course_id => summary
     */
    public function summaries(array $courseIds): Collection
    {
        return $this->activeEntries($courseIds)
            ->groupBy('course_id')
            ->map(fn (Collection $rows) => CourseLedgerCalculator::summarize($rows->map(fn (CourseLedgerEntry $e) => [
                'type' => $e->entry_type, 'channel' => $e->channel, 'staff_id' => $e->staff_id, 'cents' => Money::toCents($e->amount),
            ])));
    }

    private function activeEntries(int|array $courseIds): Collection
    {
        return CourseLedgerEntry::query()
            ->whereIn('course_id', (array) $courseIds ?: [0])
            ->whereNull('voided_at')
            ->get(['id', 'course_id', 'entry_type', 'channel', 'staff_id', 'amount']);
    }
}
