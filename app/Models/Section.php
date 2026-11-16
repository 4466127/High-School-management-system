<?php

namespace App\Models;

use App\Enums\EnrollmentStatus;
use App\Enums\Shift;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Casts\Attribute;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

/** بخش یک صنف در یک سال تحصیلی (مثلاً صنف هفتم «الف»، صبح). */
#[Fillable(['academic_year_id', 'grade_level_id', 'name', 'shift', 'class_teacher_id', 'room', 'capacity'])]
class Section extends Model
{
    protected function casts(): array
    {
        return ['shift' => Shift::class];
    }

    public function academicYear(): BelongsTo
    {
        return $this->belongsTo(AcademicYear::class);
    }

    public function gradeLevel(): BelongsTo
    {
        return $this->belongsTo(GradeLevel::class);
    }

    public function classTeacher(): BelongsTo
    {
        return $this->belongsTo(Staff::class, 'class_teacher_id');
    }

    public function enrollments(): HasMany
    {
        return $this->hasMany(Enrollment::class);
    }

    public function activeEnrollments(): HasMany
    {
        return $this->hasMany(Enrollment::class)->where('enrollments.status', EnrollmentStatus::Active);
    }

    public function attendanceSessions(): HasMany
    {
        return $this->hasMany(AttendanceSession::class);
    }

    public function assignments(): HasMany
    {
        return $this->hasMany(TeacherAssignment::class);
    }

    /** «صنف هفتم — الف (صبح)» */
    protected function label(): Attribute
    {
        return Attribute::get(fn () => ($this->gradeLevel?->name ?? '').' — '.$this->name.' ('.$this->shift->label().')');
    }

    /** نام کوتاه: «صنف هفتم الف» */
    protected function shortLabel(): Attribute
    {
        return Attribute::get(fn () => trim(($this->gradeLevel?->name ?? '').' '.$this->name));
    }

    /**
     * به ترتیب صنف، شیفت و نام. ستون‌ها فقط وقتی sections.* گذاشته می‌شوند که هنوز چیزی انتخاب نشده باشد؛
     * در غیر آن ستون‌های withCount (مثلاً active_enrollments_count) از بین می‌روند.
     */
    public function scopeOrdered(Builder $query): Builder
    {
        if ($query->getQuery()->columns === null) {
            $query->select('sections.*');
        }

        return $query
            ->join('grade_levels', 'grade_levels.id', '=', 'sections.grade_level_id')
            ->orderBy('grade_levels.sort_order')
            ->orderBy('sections.shift')
            ->orderBy('sections.name');
    }

    /** آیا این کارمند نگران صنف یا معلم یکی از مضامین این بخش است؟ */
    public function isTaughtBy(?int $staffId): bool
    {
        if (! $staffId) {
            return false;
        }

        return $this->class_teacher_id === $staffId
            || $this->assignments()->where('staff_id', $staffId)->exists();
    }
}
