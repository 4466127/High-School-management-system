<?php

namespace App\Models;

use App\Enums\EnrollmentStatus;
use App\Enums\FinalResult;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

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
            'final_result' => FinalResult::class,
            'finalized_at' => 'datetime',
            'final_snapshot' => 'array',
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

    public function attendanceRecords(): HasMany
    {
        return $this->hasMany(AttendanceRecord::class);
    }

    public function marks(): HasMany
    {
        return $this->hasMany(Mark::class);
    }

    public function isFinalized(): bool
    {
        return $this->finalized_at !== null;
    }

    public function isActive(): bool
    {
        return $this->status === EnrollmentStatus::Active;
    }
}
