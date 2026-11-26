<?php

namespace App\Models;

use App\Enums\PlanItemStatus;
use App\Enums\PlanStatus;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Carbon;

/** پلان تدریس یک مضمون در یک بخش (مرحله‌ی ۱۶). فقط از راه TeachingPlanService؛ حذف نمی‌شود. */
#[Fillable(['notes'])]
class TeachingPlan extends Model
{
    protected function casts(): array
    {
        return ['status' => PlanStatus::class, 'submitted_at' => 'datetime', 'reviewed_at' => 'datetime'];
    }

    public function section(): BelongsTo
    {
        return $this->belongsTo(Section::class);
    }

    public function subject(): BelongsTo
    {
        return $this->belongsTo(Subject::class);
    }

    public function teacher(): BelongsTo
    {
        return $this->belongsTo(Staff::class, 'staff_id')->withTrashed();
    }

    public function reviewer(): BelongsTo
    {
        return $this->belongsTo(User::class, 'reviewed_by');
    }

    public function items(): HasMany
    {
        return $this->hasMany(TeachingPlanItem::class)->orderBy('sort_order')->orderBy('id');
    }

    /**
     * پیشرفت از سطرهای بارشده: شمار، تمام‌شده (نیمه = نیم)، عقب‌مانده (تاریخ ختم گذشته و تمام نشده).
     *
     * @return array{total: int, done: int, partial: int, skipped: int, overdue: int, percent: int}
     */
    public function progress(?Carbon $today = null): array
    {
        $today ??= now();
        $items = $this->relationLoaded('items') ? $this->items : $this->items()->get();
        $counted = $items->reject(fn ($i) => $i->status === PlanItemStatus::Skipped);
        $done = $items->where('status', PlanItemStatus::Done)->count();
        $partial = $items->where('status', PlanItemStatus::Partial)->count();

        return [
            'total' => $items->count(),
            'done' => $done,
            'partial' => $partial,
            'skipped' => $items->count() - $counted->count(),
            'overdue' => $items->filter(fn ($i) => $i->isOverdue($today))->count(),
            'percent' => $counted->count() > 0 ? (int) floor(($done + $partial / 2) * 100 / $counted->count()) : 0,
        ];
    }
}
