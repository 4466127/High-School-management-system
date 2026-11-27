<?php

namespace App\Services;

use App\Enums\ActivityStatus;
use App\Enums\EnrollmentStatus;
use App\Enums\LabEventType;
use App\Models\Enrollment;
use App\Models\Lab;
use App\Models\LabItem;
use App\Models\LabItemEvent;
use App\Models\PracticalActivity;
use App\Models\PracticalActivityStudent;
use App\Models\Section;
use App\Models\Subject;
use App\Models\TeacherAssignment;
use App\Models\User;
use App\Support\ModelChanges;
use App\Support\Quantity;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

/**
 * لابراتوار (مرحله‌ی ۱۸). همه‌ی تغییرها از این‌جا (transaction + قفل + ثبت رویداد).
 * - موجودی: مقدار هر قلم فقط با رویداد دفتر (lab_item_events) عوض می‌شود؛ رویداد ویرایش/حذف نمی‌شود، اشتباه با «شمارش» اصلاح می‌شود.
 *   رویداد: رسید (+)، مصرف/گم/دور انداختن (−)، خراب (از قابل استفاده به خراب)، ترمیم (از خراب به قابل استفاده)، از بین رفتن خراب، شمارش (= مقدار شمرده).
 *   lab_item_events.quantity = اندازه‌ی رویداد (مثبت؛ شمارش با علامت)، balance_after = مقدار قابل استفاده بعد از آن.
 * - فعالیت عملی: پلان (جلوگیری از دو فعالیت در یک لابراتوار، یک روز و یک ساعت)، ثبت حاضری/نمره/نتیجه، مصرف مواد، لغو با دلیل.
 */
class LabService
{
    /** رویدادهایی که یادداشت (دلیل) لازم دارند */
    public const NEEDS_NOTE = [LabEventType::Damaged, LabEventType::WrittenOff, LabEventType::Lost, LabEventType::Disposed, LabEventType::Adjusted];

    public function __construct(private readonly ActivityLogger $logger) {}

    // ------------------------------------------------------------------ لابراتوارها

    public function saveLab(?Lab $lab, array $data, User $user): Lab
    {
        return DB::transaction(function () use ($lab, $data, $user) {
            $isNew = ! $lab;
            $lab ??= new Lab;
            $lab->fill($data);
            [$old, $new] = ModelChanges::capture($lab);
            $lab->save();
            if ($isNew || $new !== []) {
                $this->logger->log($isNew ? 'lab.created' : 'lab.updated', $lab, $lab->name, $isNew ? [] : $old, $isNew ? [] : $new, $user);
            }

            return $lab;
        });
    }

    // ------------------------------------------------------------------ اقلام موجودی

    /** @param  int  $initial  مقدار اول (صدم) */
    public function createItem(array $data, int $initial, User $user): LabItem
    {
        return DB::transaction(function () use ($data, $initial, $user) {
            $item = new LabItem($data);
            $item->forceFill(['quantity' => '0.00', 'damaged_quantity' => '0.00', 'created_by' => $user->id])->save();
            $this->logger->log('lab.item_created', $item, $item->name, user: $user);
            if ($initial > 0) {
                $this->move($item, LabEventType::Received, $initial, null, __('labs.initial_stock'), $user);
            }

            return $item;
        });
    }

    public function updateItem(LabItem $item, array $data, User $user): void
    {
        DB::transaction(function () use ($item, $data, $user) {
            $item->fill($data);
            [$old, $new] = ModelChanges::capture($item);
            $item->save();
            if ($new !== []) {
                $this->logger->log('lab.item_updated', $item, $item->name, $old, $new, $user);
            }
        });
    }

    /**
     * یک رویداد دفتر موجودی.
     *
     * @param  int  $amount  صدم؛ برای «شمارش» = مقدار شمرده‌شده‌ی قابل استفاده
     */
    public function move(LabItem $item, LabEventType $type, int $amount, ?string $date, ?string $note, User $user, ?PracticalActivity $activity = null): LabItemEvent
    {
        $note = trim((string) $note);
        if (in_array($type, self::NEEDS_NOTE, true) && $note === '') {
            throw ValidationException::withMessages(['note' => __('labs.note_required')]);
        }
        if ($amount < 0 || ($amount === 0 && $type !== LabEventType::Adjusted)) {
            throw ValidationException::withMessages(['amount' => __('labs.amount_positive')]);
        }

        return DB::transaction(function () use ($item, $type, $amount, $date, $note, $user, $activity) {
            $item = LabItem::whereKey($item->id)->lockForUpdate()->firstOrFail();
            $q = Quantity::toHundredths($item->quantity);
            $d = Quantity::toHundredths($item->damaged_quantity);
            $oldQ = $q;
            $size = $amount;

            switch ($type) {
                case LabEventType::Received:
                    $q += $amount;
                    break;
                case LabEventType::Used:
                case LabEventType::Lost:
                case LabEventType::Disposed:
                    $this->ensureEnough($q, $amount, $item);
                    $q -= $amount;
                    break;
                case LabEventType::Damaged:
                    $this->ensureEnough($q, $amount, $item);
                    $q -= $amount;
                    $d += $amount;
                    break;
                case LabEventType::Repaired:
                case LabEventType::WrittenOff:
                    if ($d < $amount) {
                        throw ValidationException::withMessages(['amount' => __('labs.not_enough_damaged', ['have' => Quantity::format(Quantity::fromHundredths($d))])]);
                    }
                    $d -= $amount;
                    if ($type === LabEventType::Repaired) {
                        $q += $amount;
                    }
                    break;
                case LabEventType::Adjusted:
                    $size = $amount - $q;   // با علامت
                    if ($size === 0) {
                        throw ValidationException::withMessages(['amount' => __('labs.adjust_same')]);
                    }
                    $q = $amount;
                    break;
            }

            $item->forceFill(['quantity' => Quantity::fromHundredths($q), 'damaged_quantity' => Quantity::fromHundredths($d)])->save();
            $event = new LabItemEvent;
            $event->forceFill([
                'lab_item_id' => $item->id, 'type' => $type, 'quantity' => Quantity::fromHundredths($size), 'balance_after' => Quantity::fromHundredths($q),
                'event_date' => $date ?: AttendanceService::today()->toDateString(), 'practical_activity_id' => $activity?->id,
                'note' => $note !== '' ? mb_substr($note, 0, 300) : null, 'user_id' => $user->id,
            ])->save();
            $this->logger->log('lab.stock_'.$type->value, $item, $item->name.' — '.Quantity::format(Quantity::fromHundredths(abs($size))).' '.$item->unit->label().($note !== '' ? ' — '.$note : ''),
                ['quantity' => Quantity::fromHundredths($oldQ)], ['quantity' => Quantity::fromHundredths($q)], $user);

            return $event;
        });
    }

    private function ensureEnough(int $have, int $need, LabItem $item): void
    {
        if ($have < $need) {
            throw ValidationException::withMessages(['amount' => __('labs.not_enough', ['have' => Quantity::format(Quantity::fromHundredths($have)), 'unit' => $item->unit->label()])]);
        }
    }

    // ------------------------------------------------------------------ فعالیت عملی

    /** @param  array<string, mixed>  $data  lab_id، period_id، activity_date، title، objectives، materials_note، max_score */
    public function planActivity(Section $section, Subject $subject, array $data, User $user): PracticalActivity
    {
        $section->loadMissing('gradeLevel.subjects');
        if (! $section->gradeLevel->subjects->contains('id', $subject->id)) {
            throw ValidationException::withMessages(['subject' => __('labs.subject_not_in_grade')]);
        }

        return DB::transaction(function () use ($section, $subject, $data, $user) {
            $this->ensureFree($data);
            $activity = new PracticalActivity($data);
            $activity->forceFill([
                'academic_year_id' => $section->academic_year_id, 'section_id' => $section->id, 'subject_id' => $subject->id,
                'staff_id' => TeacherAssignment::where('section_id', $section->id)->where('subject_id', $subject->id)->value('staff_id') ?? $user->staff_id,
                'status' => ActivityStatus::Planned, 'created_by' => $user->id,
            ])->save();
            $this->logger->log('lab.activity_planned', $activity, $this->label($activity), user: $user);

            return $activity;
        });
    }

    public function updateActivity(PracticalActivity $activity, array $data, User $user): void
    {
        if ($activity->status !== ActivityStatus::Planned) {
            throw ValidationException::withMessages(['activity' => __('labs.not_planned')]);
        }
        DB::transaction(function () use ($activity, $data, $user) {
            $this->ensureFree($data, $activity->id);
            $activity->fill($data);
            [$old, $new] = ModelChanges::capture($activity);
            $activity->save();
            if ($new !== []) {
                $this->logger->log('lab.activity_updated', $activity, $this->label($activity), $old, $new, $user);
            }
        });
    }

    /**
     * ثبت انجام فعالیت: حاضری (و نمره اگر max_score هست) و مشاهده/نتیجه. دوباره ثبت = اصلاح.
     *
     * @param  array<int, array{present?: bool, score?: ?string, note?: ?string}>  $rows  enrollment_id => …
     */
    public function recordResult(PracticalActivity $activity, array $rows, ?string $resultNote, User $user): void
    {
        if ($activity->status === ActivityStatus::Cancelled) {
            throw ValidationException::withMessages(['activity' => __('labs.is_cancelled')]);
        }
        $enrolled = Enrollment::where('section_id', $activity->section_id)->where('status', EnrollmentStatus::Active)->pluck('id')->flip();
        DB::transaction(function () use ($activity, $rows, $resultNote, $user, $enrolled) {
            foreach ($rows as $enrollmentId => $row) {
                if (! $enrolled->has((int) $enrollmentId)) {
                    continue;
                }
                $score = isset($row['score']) && $row['score'] !== '' && $row['score'] !== null ? (float) $row['score'] : null;
                if ($score !== null && ($activity->max_score === null || $score < 0 || $score > $activity->max_score)) {
                    throw ValidationException::withMessages(['rows' => __('labs.score_range', ['max' => $activity->max_score ?? 0])]);
                }
                $present = ! empty($row['present']);
                PracticalActivityStudent::query()->updateOrCreate(
                    ['practical_activity_id' => $activity->id, 'enrollment_id' => (int) $enrollmentId],
                    ['present' => $present, 'score' => $present && $score !== null ? number_format($score, 2, '.', '') : null, 'note' => ($n = trim((string) ($row['note'] ?? ''))) !== '' ? mb_substr($n, 0, 200) : null],
                );
            }
            $wasDone = $activity->status === ActivityStatus::Done;
            $activity->forceFill(['status' => ActivityStatus::Done, 'result_note' => ($r = trim((string) $resultNote)) !== '' ? mb_substr($r, 0, 1000) : null])->save();
            $this->logger->log($wasDone ? 'lab.activity_corrected' : 'lab.activity_done', $activity, $this->label($activity), new: [
                'present' => $activity->students()->where('present', true)->count(), 'absent' => $activity->students()->where('present', false)->count(),
            ], user: $user);
        });
    }

    public function cancelActivity(PracticalActivity $activity, string $reason, User $user): void
    {
        if ($activity->status !== ActivityStatus::Planned) {
            throw ValidationException::withMessages(['activity' => __('labs.not_planned')]);
        }
        $activity->forceFill(['status' => ActivityStatus::Cancelled, 'cancel_reason' => mb_substr(trim($reason), 0, 300)])->save();
        $this->logger->log('lab.activity_cancelled', $activity, $this->label($activity).' — '.$reason, user: $user);
    }

    /** مواد مصرف‌شده در فعالیت (رویداد «مصرف» با پیوند به فعالیت) */
    public function useMaterial(PracticalActivity $activity, LabItem $item, int $amount, User $user): LabItemEvent
    {
        if ($activity->status === ActivityStatus::Cancelled) {
            throw ValidationException::withMessages(['activity' => __('labs.is_cancelled')]);
        }
        if ($item->lab_id !== $activity->lab_id) {
            throw ValidationException::withMessages(['item' => __('labs.item_other_lab')]);
        }

        return $this->move($item, LabEventType::Used, $amount, $activity->activity_date->toDateString(), $activity->title, $user, $activity);
    }

    /** دو فعالیت در یک لابراتوار، یک روز و یک ساعت نمی‌شود (لغوشده حساب نمی‌شود) */
    private function ensureFree(array $data, ?int $ignore = null): void
    {
        if (empty($data['period_id'])) {
            return;
        }
        Lab::whereKey($data['lab_id'])->lockForUpdate()->first();
        $clash = PracticalActivity::with(['section.gradeLevel', 'subject'])
            ->where('lab_id', $data['lab_id'])->where('activity_date', $data['activity_date'])->where('period_id', $data['period_id'])
            ->where('status', '!=', ActivityStatus::Cancelled->value)->when($ignore, fn ($q) => $q->where('id', '!=', $ignore))->first();
        if ($clash) {
            throw ValidationException::withMessages(['period_id' => __('labs.lab_busy', ['activity' => $this->label($clash)])]);
        }
    }

    private function label(PracticalActivity $a): string
    {
        $a->loadMissing(['section.gradeLevel', 'subject']);

        return $a->title.' — '.$a->subject->name.' '.$a->section->gradeLevel->name.' '.$a->section->name;
    }
}
