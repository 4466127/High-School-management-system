<?php

namespace App\Models;

use App\Enums\ExamStatus;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

/** یک امتحان در یک سال تحصیلی (برای همه‌ی بخش‌ها). */
#[Fillable(['academic_year_id', 'academic_term_id', 'exam_type_id', 'name', 'start_date', 'end_date', 'max_mark', 'weight', 'counts_in_final', 'status'])]
class Exam extends Model
{
    protected function casts(): array
    {
        return [
            'start_date' => 'date',
            'end_date' => 'date',
            'max_mark' => 'decimal:2',
            'weight' => 'decimal:2',
            'counts_in_final' => 'boolean',
            'status' => ExamStatus::class,
        ];
    }

    public function academicYear(): BelongsTo
    {
        return $this->belongsTo(AcademicYear::class);
    }

    public function term(): BelongsTo
    {
        return $this->belongsTo(AcademicTerm::class, 'academic_term_id');
    }

    public function type(): BelongsTo
    {
        return $this->belongsTo(ExamType::class, 'exam_type_id');
    }

    public function marks(): HasMany
    {
        return $this->hasMany(Mark::class);
    }

    /** به ترتیب تاریخ و سپس ترتیب نوع */
    public function scopeOrdered(Builder $query): Builder
    {
        return $query->orderByRaw('start_date IS NULL, start_date')->orderBy('id');
    }

    public function isOpen(): bool
    {
        return $this->status === ExamStatus::Open;
    }

    public function isLocked(): bool
    {
        return $this->status === ExamStatus::Locked;
    }
}
