<?php

namespace App\Models;

use App\Enums\EnrollmentStatus;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * شمولیت سالانه‌ی یک دانش‌آموز در یک بخش (یک سطر برای هر سال).
 * حاضری و نمرات مرحله‌های بعد به همین سطر وصل می‌شوند؛ پس تاریخچه همیشه می‌ماند.
 * تغییرها فقط از راه App\Services\EnrollmentService.
 */
#[Fillable(['student_id', 'academic_year_id', 'section_id', 'roll_no', 'enrolled_on', 'status', 'left_on', 'leave_reason'])]
class Enrollment extends Model
{
    protected function casts(): array
    {
        return [
            'status' => EnrollmentStatus::class,
            'enrolled_on' => 'date',
            'left_on' => 'date',
            'final_total' => 'decimal:2',
            'final_average' => 'decimal:2',
            'finalized_at' => 'datetime',
        ];
    }

    public function student(): BelongsTo
    {
        return $this->belongsTo(Student::class)->withTrashed();
    }

    public function academicYear(): BelongsTo
    {
        return $this->belongsTo(AcademicYear::class);
    }

    public function section(): BelongsTo
    {
        return $this->belongsTo(Section::class);
    }

    public function isActive(): bool
    {
        return $this->status === EnrollmentStatus::Active;
    }
}
