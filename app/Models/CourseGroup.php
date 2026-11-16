<?php

namespace App\Models;

use App\Enums\ParticipantStatus;
use App\Enums\Shift;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Casts\Attribute;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

/** گروه/صنف یک کورس: نوبت (قبل/بعد از ظهر)، معلم و زمان درس. حاضری برای هر گروه گرفته می‌شود. */
#[Fillable(['course_id', 'name', 'shift', 'staff_id', 'schedule', 'is_active'])]
class CourseGroup extends Model
{
    protected function casts(): array
    {
        return [
            'shift' => Shift::class,
            'is_active' => 'boolean',
        ];
    }

    public function course(): BelongsTo
    {
        return $this->belongsTo(Course::class);
    }

    public function teacher(): BelongsTo
    {
        return $this->belongsTo(Staff::class, 'staff_id')->withTrashed();
    }

    public function participants(): HasMany
    {
        return $this->hasMany(CourseParticipant::class);
    }

    public function activeParticipants(): HasMany
    {
        return $this->participants()->where('status', ParticipantStatus::Active);
    }

    public function sessions(): HasMany
    {
        return $this->hasMany(CourseSession::class);
    }

    /** «انگلیسی — گروه اول (قبل از ظهر)» */
    protected function label(): Attribute
    {
        return Attribute::get(fn () => $this->course->name.' — '.$this->name.' ('.$this->shift->label().')');
    }
}
