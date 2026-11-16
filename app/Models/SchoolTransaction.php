<?php

namespace App\Models;

use App\Enums\TransactionType;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/** درآمد یا مصرف عمومی مکتب (جدا از فیس). باطل می‌شود، حذف نمی‌شود. */
#[Fillable(['type', 'finance_category_id', 'amount', 'date', 'party_name', 'description', 'recorded_by'])]
class SchoolTransaction extends Model
{
    protected function casts(): array
    {
        return [
            'type' => TransactionType::class,
            'amount' => 'decimal:2',
            'date' => 'date',
            'voided_at' => 'datetime',
        ];
    }

    public function category(): BelongsTo
    {
        return $this->belongsTo(FinanceCategory::class, 'finance_category_id');
    }

    public function recorder(): BelongsTo
    {
        return $this->belongsTo(User::class, 'recorded_by');
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
