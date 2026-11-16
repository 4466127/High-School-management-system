<?php

namespace App\Models;

use App\Enums\CourseChannel;
use App\Enums\CourseEntryType;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/** سطر دفتر مالی کورس (کمک، معاش معلم، مصرف). باطل می‌شود، حذف نمی‌شود. فقط از راه App\Services\CourseLedgerService. */
#[Fillable(['course_id', 'entry_type', 'channel', 'staff_id', 'amount', 'date', 'donor_name', 'description', 'recorded_by'])]
class CourseLedgerEntry extends Model
{
    protected function casts(): array
    {
        return [
            'entry_type' => CourseEntryType::class,
            'channel' => CourseChannel::class,
            'amount' => 'decimal:2',
            'date' => 'date',
            'voided_at' => 'datetime',
        ];
    }

    public function course(): BelongsTo
    {
        return $this->belongsTo(Course::class);
    }

    public function teacher(): BelongsTo
    {
        return $this->belongsTo(Staff::class, 'staff_id')->withTrashed();
    }

    public function recorder(): BelongsTo
    {
        return $this->belongsTo(User::class, 'recorded_by');
    }

    public function isVoided(): bool
    {
        return $this->voided_at !== null;
    }
}
