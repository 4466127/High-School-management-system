<?php

namespace App\Models;

use App\Enums\FinalResult;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * نتیجه‌ی یک سال که در سیستم ثبت نیست (درج دستی؛ مرحله‌ی ۱۴). فقط از راه PastResultService.
 * حذف نمی‌شود؛ اشتباه باطل می‌شود.
 */
#[Fillable(['grade_level_id', 'grade_name', 'year_name', 'school_name', 'total', 'average', 'result', 'grade_letter', 'subjects', 'note'])]
class StudentPastResult extends Model
{
    protected function casts(): array
    {
        return [
            'total' => 'decimal:2',
            'average' => 'decimal:2',
            'result' => FinalResult::class,
            'subjects' => 'array',
            'cancelled_at' => 'datetime',
        ];
    }

    public function student(): BelongsTo
    {
        return $this->belongsTo(Student::class)->withTrashed();
    }

    public function gradeLevel(): BelongsTo
    {
        return $this->belongsTo(GradeLevel::class);
    }

    public function creator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    public function scopeActive(Builder $query): void
    {
        $query->whereNull('cancelled_at');
    }

    public function isCancelled(): bool
    {
        return $this->cancelled_at !== null;
    }
}
