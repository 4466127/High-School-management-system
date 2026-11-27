<?php

namespace App\Models;

use App\Enums\LabEventType;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/** یک سطر دفتر موجودی (مرحله‌ی ۱۸). فقط LabService می‌نویسد؛ ویرایش و حذف نمی‌شود (اشتباه با «شمارش» اصلاح می‌شود). */
class LabItemEvent extends Model
{
    protected $guarded = ['*'];

    protected function casts(): array
    {
        return ['type' => LabEventType::class, 'quantity' => 'decimal:2', 'balance_after' => 'decimal:2', 'event_date' => 'date'];
    }

    public function item(): BelongsTo
    {
        return $this->belongsTo(LabItem::class, 'lab_item_id');
    }

    public function activity(): BelongsTo
    {
        return $this->belongsTo(PracticalActivity::class, 'practical_activity_id');
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }
}
