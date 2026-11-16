<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

/** حاضری یک گروه کورس در یک روز. تغییرها فقط از راه App\Services\CourseService. */
#[Fillable(['course_group_id', 'date', 'taken_by', 'updated_by'])]
class CourseSession extends Model
{
    protected function casts(): array
    {
        return ['date' => 'date'];
    }

    public function group(): BelongsTo
    {
        return $this->belongsTo(CourseGroup::class, 'course_group_id');
    }

    public function records(): HasMany
    {
        return $this->hasMany(CourseAttendance::class);
    }

    public function takenBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'taken_by');
    }

    public function updatedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'updated_by');
    }
}
