<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

/** رسید پرداخت با شماره‌ی پشت‌سرهم. جمع تخصیص‌ها = مبلغ رسید. باطل می‌شود، حذف نمی‌شود. */
#[Fillable(['receipt_no', 'student_id', 'paid_on', 'amount', 'payer_name', 'note', 'received_by'])]
class Payment extends Model
{
    protected function casts(): array
    {
        return [
            'paid_on' => 'date',
            'amount' => 'decimal:2',
            'voided_at' => 'datetime',
        ];
    }

    public function student(): BelongsTo
    {
        return $this->belongsTo(Student::class)->withTrashed();
    }

    public function allocations(): HasMany
    {
        return $this->hasMany(PaymentAllocation::class);
    }

    public function receiver(): BelongsTo
    {
        return $this->belongsTo(User::class, 'received_by');
    }

    public function voidedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'voided_by');
    }

    public function isVoided(): bool
    {
        return $this->voided_at !== null;
    }
}
