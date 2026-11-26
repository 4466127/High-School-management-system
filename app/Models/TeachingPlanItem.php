<?php

namespace App\Models;

use App\Enums\PlanItemStatus;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Carbon;

/** یک موضوع (درس) پلان تدریس با پیشرفت آن (مرحله‌ی ۱۶). */
#[Fillable(['sort_order', 'week_no', 'planned_from', 'planned_to', 'topic', 'objectives', 'planned_hours', 'note'])]
class TeachingPlanItem extends Model
{
    protected function casts(): array
    {
        return [
            'status' => PlanItemStatus::class,
            'planned_from' => 'date',
            'planned_to' => 'date',
            'completed_on' => 'date',
        ];
    }

    public function plan(): BelongsTo
    {
        return $this->belongsTo(TeachingPlan::class, 'teaching_plan_id');
    }

    /** تاریخ ختم گذشته و هنوز «تمام» یا «رها» نشده */
    public function isOverdue(?Carbon $today = null): bool
    {
        return $this->planned_to !== null
            && $this->planned_to->lt(($today ?? now())->startOfDay())
            && in_array($this->status, [PlanItemStatus::Planned, PlanItemStatus::Partial], true);
    }
}
