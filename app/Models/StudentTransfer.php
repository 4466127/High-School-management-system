<?php

namespace App\Models;

use App\Enums\TransferDirection;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * انتقال یک دانش‌آموز به مکتب دیگر یا از مکتب دیگر (مرحله‌ی ۱۳). فقط از راه TransferService.
 * حذف نمی‌شود؛ باطل می‌شود. سوابق تحصیلی دانش‌آموز (enrollments) هرگز پاک نمی‌شود.
 */
#[Fillable([
    'other_school', 'other_province', 'other_district', 'requested_on', 'approved_on', 'transfer_date', 'reason',
    'reference_number', 'last_grade', 'clearance_note', 'notes',
])]
class StudentTransfer extends Model
{
    protected function casts(): array
    {
        return [
            'direction' => TransferDirection::class,
            'requested_on' => 'date',
            'approved_on' => 'date',
            'transfer_date' => 'date',
            'outstanding_balance' => 'decimal:2',
            'previous_records' => 'array',
            'cancelled_at' => 'datetime',
        ];
    }

    public function student(): BelongsTo
    {
        return $this->belongsTo(Student::class)->withTrashed();
    }

    public function enrollment(): BelongsTo
    {
        return $this->belongsTo(Enrollment::class);
    }

    public function creator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    public function canceller(): BelongsTo
    {
        return $this->belongsTo(User::class, 'cancelled_by');
    }

    public function isCancelled(): bool
    {
        return $this->status === 'cancelled';
    }

    public function isOut(): bool
    {
        return $this->direction === TransferDirection::Out;
    }
}
