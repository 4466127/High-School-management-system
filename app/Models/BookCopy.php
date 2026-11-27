<?php

namespace App\Models;

use App\Enums\CopyCondition;
use App\Enums\CopySource;
use App\Enums\CopyStatus;
use App\Enums\LoanStatus;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;

/** یک جلد فزیکی با نمبر ثبت (مرحله‌ی ۱۷). حذف نمی‌شود؛ وضعیت فقط از راه LibraryService. */
#[Fillable(['condition', 'source', 'acquired_on', 'price', 'notes'])]
class BookCopy extends Model
{
    protected function casts(): array
    {
        return [
            'status' => CopyStatus::class, 'condition' => CopyCondition::class, 'source' => CopySource::class,
            'acquired_on' => 'date', 'status_changed_on' => 'date', 'price' => 'decimal:2',
        ];
    }

    public function book(): BelongsTo
    {
        return $this->belongsTo(Book::class);
    }

    public function loans(): HasMany
    {
        return $this->hasMany(BookLoan::class)->latest('id');
    }

    public function activeLoan(): HasOne
    {
        return $this->hasOne(BookLoan::class)->where('status', LoanStatus::Active->value)->latestOfMany();
    }
}
