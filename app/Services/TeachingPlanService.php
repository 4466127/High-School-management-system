<?php

namespace App\Services;

use App\Enums\PlanItemStatus;
use App\Enums\PlanStatus;
use App\Models\Section;
use App\Models\Subject;
use App\Models\TeacherAssignment;
use App\Models\TeachingPlan;
use App\Models\TeachingPlanItem;
use App\Models\User;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

/**
 * پلان تدریس و پیشرفت تدریس (مرحله‌ی ۱۶). همه‌ی تغییرها از این‌جا (transaction + ثبت رویداد).
 * گردش: پیش‌نویس ← فرستادن به مدیر ← منظور، یا برگشت با یادداشت برای اصلاح.
 * موضوع‌ها فقط در پیش‌نویس/برگشت افزوده، ویرایش یا حذف می‌شوند؛ پیشرفت (تمام، نیمه، رها) همیشه ثبت می‌شود.
 */
class TeachingPlanService
{
    /** بیشترین شمار موضوع در یک بار افزودن */
    public const MAX_TOPICS = 80;

    public function __construct(private readonly ActivityLogger $logger) {}

    /** پلان این بخش و مضمون؛ اگر نیست ساخته می‌شود (معلم از «معلمان مضامین») */
    public function open(Section $section, Subject $subject, User $user): TeachingPlan
    {
        $section->loadMissing('gradeLevel.subjects');
        if (! $section->gradeLevel->subjects->contains('id', $subject->id)) {
            throw ValidationException::withMessages(['subject' => __('teaching.subject_not_in_grade')]);
        }

        return DB::transaction(function () use ($section, $subject, $user) {
            $plan = TeachingPlan::where('section_id', $section->id)->where('subject_id', $subject->id)->lockForUpdate()->first();
            if ($plan) {
                return $plan;
            }
            $plan = new TeachingPlan;
            $plan->forceFill([
                'academic_year_id' => $section->academic_year_id, 'section_id' => $section->id, 'subject_id' => $subject->id,
                'staff_id' => TeacherAssignment::where('section_id', $section->id)->where('subject_id', $subject->id)->value('staff_id') ?? $user->staff_id,
                'status' => PlanStatus::Draft, 'created_by' => $user->id,
            ])->save();
            $this->logger->log('teaching.plan_created', $plan, $this->label($plan), user: $user);

            return $plan;
        });
    }

    public function updateNotes(TeachingPlan $plan, ?string $notes, User $user): void
    {
        $old = (string) $plan->notes;
        $plan->forceFill(['notes' => ($notes = trim((string) $notes)) !== '' ? mb_substr($notes, 0, 1000) : null])->save();
        if ($old !== (string) $plan->notes) {
            $this->logger->log('teaching.plan_updated', $plan, $this->label($plan), ['notes' => $old], ['notes' => (string) $plan->notes], $user);
        }
    }

    /**
     * افزودن چند موضوع یک‌جا: هر سطر متن یک موضوع. اگر تاریخ شروع داده شود، هر موضوع یک هفته (۷ روز) می‌گیرد.
     *
     * @return int شمار موضوع‌های افزوده
     */
    public function addTopics(TeachingPlan $plan, string $text, ?string $startDate, int $hours, User $user): int
    {
        $this->ensureEditable($plan);
        $lines = collect(preg_split('/\R/u', $text))->map(fn ($l) => trim(preg_replace('/^\s*([0-9۰-۹]+[\.\)\-]|[-•*])\s*/u', '', (string) $l)))->filter()->values();
        if ($lines->isEmpty()) {
            throw ValidationException::withMessages(['topics' => __('teaching.no_topics')]);
        }
        if ($lines->count() > self::MAX_TOPICS) {
            throw ValidationException::withMessages(['topics' => __('teaching.too_many_topics', ['max' => self::MAX_TOPICS])]);
        }

        return DB::transaction(function () use ($plan, $lines, $startDate, $hours, $user) {
            $last = $plan->items()->reorder()->orderByDesc('sort_order')->first();
            $order = (int) ($last?->sort_order ?? 0);
            $week = (int) ($last?->week_no ?? 0);
            $start = $startDate ? Carbon::parse($startDate)->startOfDay() : null;
            foreach ($lines as $i => $topic) {
                $item = new TeachingPlanItem([
                    'sort_order' => ++$order,
                    'week_no' => ++$week,
                    'topic' => mb_substr($topic, 0, 200),
                    'planned_hours' => $hours > 0 ? $hours : null,
                    'planned_from' => $start?->copy()->addWeeks($i)->toDateString(),
                    'planned_to' => $start?->copy()->addWeeks($i)->addDays(6)->toDateString(),
                ]);
                $item->teaching_plan_id = $plan->id;
                $item->status = PlanItemStatus::Planned;
                $item->save();
            }
            $this->logger->log('teaching.topics_added', $plan, $this->label($plan), new: ['count' => $lines->count()], user: $user);

            return $lines->count();
        });
    }

    /** @param array{week_no?: ?int, planned_from?: ?string, planned_to?: ?string, topic: string, objectives?: ?string, planned_hours?: ?int} $data */
    public function updateItem(TeachingPlanItem $item, array $data, User $user): void
    {
        $this->ensureEditable($item->plan);
        DB::transaction(function () use ($item, $data, $user) {
            $old = $item->only(['topic', 'week_no', 'planned_hours']);
            $item->fill($data)->save();
            $this->logger->log('teaching.topic_updated', $item->plan, $this->label($item->plan), $old, $item->only(['topic', 'week_no', 'planned_hours']), $user);
        });
    }

    public function removeItem(TeachingPlanItem $item, User $user): void
    {
        $this->ensureEditable($item->plan);
        DB::transaction(function () use ($item, $user) {
            $plan = $item->plan;
            $topic = $item->topic;
            $item->delete();
            $this->logger->log('teaching.topic_removed', $plan, $this->label($plan).' — '.$topic, user: $user);
        });
    }

    /** پیشرفت یک موضوع (در هر وضعیت پلان) */
    public function setProgress(TeachingPlanItem $item, PlanItemStatus $status, ?string $completedOn, ?string $note, User $user): void
    {
        DB::transaction(function () use ($item, $status, $completedOn, $note, $user) {
            $old = ['status' => $item->status->value];
            $item->forceFill([
                'status' => $status,
                'completed_on' => in_array($status, [PlanItemStatus::Done, PlanItemStatus::Partial], true) ? ($completedOn ?: now()->toDateString()) : null,
                'note' => ($note = trim((string) $note)) !== '' ? mb_substr($note, 0, 300) : null,
            ])->save();
            $this->logger->log('teaching.progress', $item->plan, $this->label($item->plan).' — '.$item->topic, $old, ['status' => $status->value], $user);
        });
    }

    public function submit(TeachingPlan $plan, User $user): void
    {
        $this->ensureEditable($plan);
        if (! $plan->items()->exists()) {
            throw ValidationException::withMessages(['plan' => __('teaching.submit_empty')]);
        }
        $this->transition($plan, PlanStatus::Submitted, ['submitted_at' => now()], 'teaching.plan_submitted', null, $user);
    }

    public function approve(TeachingPlan $plan, ?string $note, User $user): void
    {
        if ($plan->status !== PlanStatus::Submitted) {
            throw ValidationException::withMessages(['plan' => __('teaching.not_submitted')]);
        }
        $this->transition($plan, PlanStatus::Approved, ['reviewed_by' => $user->id, 'reviewed_at' => now(), 'review_note' => $note ?: null], 'teaching.plan_approved', $note, $user);
    }

    /** برگشت برای اصلاح (از فرستاده یا منظورشده) — یادداشت لازم است */
    public function returnPlan(TeachingPlan $plan, string $note, User $user): void
    {
        if (! in_array($plan->status, [PlanStatus::Submitted, PlanStatus::Approved], true)) {
            throw ValidationException::withMessages(['plan' => __('teaching.not_submitted')]);
        }
        $this->transition($plan, PlanStatus::Returned, ['reviewed_by' => $user->id, 'reviewed_at' => now(), 'review_note' => $note], 'teaching.plan_returned', $note, $user);
    }

    /**
     * کاپی موضوع‌های یک پلان (مثلاً همان مضمون در بخش دیگر همین صنف) به پلانی که هنوز موضوع ندارد.
     *
     * @return int شمار موضوع‌ها
     */
    public function copyFrom(TeachingPlan $source, TeachingPlan $target, User $user): int
    {
        $this->ensureEditable($target);
        if ($source->id === $target->id || $source->subject_id !== $target->subject_id) {
            throw ValidationException::withMessages(['from' => __('teaching.copy_mismatch')]);
        }
        if ($target->items()->exists()) {
            throw ValidationException::withMessages(['from' => __('teaching.copy_not_empty')]);
        }

        return DB::transaction(function () use ($source, $target, $user) {
            $count = 0;
            foreach ($source->items as $s) {
                $item = new TeachingPlanItem($s->only(['sort_order', 'week_no', 'planned_from', 'planned_to', 'topic', 'objectives', 'planned_hours']));
                $item->teaching_plan_id = $target->id;
                $item->status = PlanItemStatus::Planned;
                $item->save();
                $count++;
            }
            $this->logger->log('teaching.plan_copied', $target, $this->label($source).' ← '.$this->label($target), new: ['count' => $count], user: $user);

            return $count;
        });
    }

    public function label(TeachingPlan $plan): string
    {
        $plan->loadMissing('section.gradeLevel', 'subject');

        return $plan->subject->name.' — '.$plan->section->label;
    }

    private function transition(TeachingPlan $plan, PlanStatus $to, array $extra, string $action, ?string $note, User $user): void
    {
        DB::transaction(function () use ($plan, $to, $extra, $action, $note, $user) {
            $old = $plan->status->value;
            $plan->forceFill(['status' => $to] + $extra)->save();
            $this->logger->log($action, $plan, $this->label($plan).($note ? ' — '.$note : ''), ['status' => $old], ['status' => $to->value], $user);
        });
    }

    private function ensureEditable(TeachingPlan $plan): void
    {
        if (! $plan->status->editable()) {
            throw ValidationException::withMessages(['plan' => __('teaching.locked', ['status' => $plan->status->label()])]);
        }
    }
}
