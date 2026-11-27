<?php

namespace App\Models;

use App\Enums\BorrowerType;
use App\Enums\CopyCondition;
use App\Enums\LoanKind;
use App\Enums\LoanStatus;
use App\Services\AttendanceService;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * امانت یک جلد (مرحله‌ی ۱۷). فقط از راه LibraryService؛ حذف نمی‌شود.
 * دیرکرد ذخیره نمی‌شود: due_on گذشته و هنوز active (امروز = کابل).
 */
class BookLoan extends Model
{
    protected $guarded = ['*'];

    protected function casts(): array
    {
        return [
            'borrower_type' => BorrowerType::class, 'kind' => LoanKind::class, 'status' => LoanStatus::class,
            'loaned_on' => 'date', 'due_on' => 'date', 'returned_on' => 'date', 'renewals' => 'integer',
        ];
    }

    public function copy(): BelongsTo
    {
        return $this->belongsTo(BookCopy::class, 'book_copy_id');
    }

    public function student(): BelongsTo
    {
        return $this->belongsTo(Student::class)->withTrashed();
    }

    public function staff(): BelongsTo
    {
        return $this->belongsTo(Staff::class)->withTrashed();
    }

    public function issuer(): BelongsTo
    {
        return $this->belongsTo(User::class, 'issued_by');
    }

    public function scopeActive(Builder $query): Builder
    {
        return $query->where('book_loans.status', LoanStatus::Active->value);
    }

    public function scopeOverdue(Builder $query): Builder
    {
        return $query->active()->where('book_loans.due_on', '<', AttendanceService::today()->toDateString());
    }

    public function borrower(): Student|Staff|null
    {
        return $this->borrower_type === BorrowerType::Student ? $this->student : $this->staff;
    }

    public function borrowerName(): string
    {
        return $this->borrower()?->full_name ?? '—';
    }

    public function isOverdue(): bool
    {
        return $this->status === LoanStatus::Active && $this->due_on->lt(AttendanceService::today()->startOfDay());
    }

    /** روزهای دیرکرد (۰ = سر وقت) */
    public function overdueDays(): int
    {
        if ($this->status !== LoanStatus::Active) {
            return $this->returned_on && $this->returned_on->gt($this->due_on) ? (int) $this->due_on->diffInDays($this->returned_on) : 0;
        }

        return $this->isOverdue() ? (int) $this->due_on->diffInDays(AttendanceService::today()->startOfDay()) : 0;
    }

    public function returnConditionLabel(): ?string
    {
        if (! $this->return_condition) {
            return null;
        }

        return $this->return_condition === 'damaged' ? __('library.condition_damaged') : CopyCondition::from($this->return_condition)->label();
    }
}
