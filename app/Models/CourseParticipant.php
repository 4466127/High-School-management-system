<?php

namespace App\Models;

use App\Enums\Gender;
use App\Enums\ParticipantStatus;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Casts\Attribute;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

/** اشتراک‌کننده‌ی کورس: دانش‌آموز مکتب (student_id) یا فرد بیرونی (نام، نام پدر، تلفن). */
#[Fillable(['course_group_id', 'student_id', 'name', 'father_name', 'phone', 'gender', 'joined_on', 'status', 'note'])]
class CourseParticipant extends Model
{
    protected function casts(): array
    {
        return [
            'status' => ParticipantStatus::class,
            'gender' => Gender::class,
            'joined_on' => 'date',
        ];
    }

    public function group(): BelongsTo
    {
        return $this->belongsTo(CourseGroup::class, 'course_group_id');
    }

    public function student(): BelongsTo
    {
        return $this->belongsTo(Student::class)->withTrashed();
    }

    public function attendance(): HasMany
    {
        return $this->hasMany(CourseAttendance::class);
    }

    public function isSchoolStudent(): bool
    {
        return $this->student_id !== null;
    }

    protected function displayName(): Attribute
    {
        return Attribute::get(fn () => $this->student?->full_name ?? (string) $this->name);
    }

    protected function displayFatherName(): Attribute
    {
        return Attribute::get(fn () => $this->student?->father_name ?? $this->father_name);
    }
}
