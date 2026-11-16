<?php

namespace App\Models;

use App\Support\Money;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * بل یک دانش‌آموز. مانده = مبلغ − تخفیف − مجموع تخصیص‌های رسیدهای باطل‌نشده (ذخیره نمی‌شود).
 * تغییرها فقط از راه App\Services\FinanceService.
 */
#[Fillable(['enrollment_id', 'student_id', 'fee_type_id', 'period_label', 'amount', 'discount', 'discount_reason', 'due_date', 'created_by'])]
class StudentInvoice extends Model
{
    protected function casts(): array
    {
        return [
            'amount' => 'decimal:2',
            'discount' => 'decimal:2',
            'due_date' => 'date',
            'voided_at' => 'datetime',
        ];
    }

    public function enrollment(): BelongsTo
    {
        return $this->belongsTo(Enrollment::class);
    }

    public function student(): BelongsTo
    {
        return $this->belongsTo(Student::class)->withTrashed();
    }

    public function feeType(): BelongsTo
    {
        return $this->belongsTo(FeeType::class);
    }

    public function allocations(): HasMany
    {
        return $this->hasMany(PaymentAllocation::class);
    }

    public function voidedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'voided_by');
    }

    /** مجموع پرداخت‌شده (فقط رسیدهای باطل‌نشده) به پول */
    public function paidCents(): int
    {
        return Money::toCents($this->allocations()
            ->whereHas('payment', fn ($q) => $q->whereNull('voided_at'))
            ->sum('amount'));
    }

    public function netCents(): int
    {
        return Money::toCents($this->amount) - Money::toCents($this->discount);
    }

    public function isVoided(): bool
    {
        return $this->voided_at !== null;
    }

    public function scopeActive(Builder $query): Builder
    {
        return $query->whereNull('student_invoices.voided_at');
    }

    /** پرداخت‌شده‌ی هر بل در ستون paid (با یک subquery؛ رسید باطل حساب نمی‌شود) */
    public function scopeWithPaid(Builder $query): Builder
    {
        return $query->addSelect(['paid' => PaymentAllocation::query()
            ->selectRaw('COALESCE(SUM(payment_allocations.amount), 0)')
            ->join('payments', 'payments.id', '=', 'payment_allocations.payment_id')
            ->whereNull('payments.voided_at')
            ->whereColumn('payment_allocations.student_invoice_id', 'student_invoices.id'),
        ]);
    }
}
