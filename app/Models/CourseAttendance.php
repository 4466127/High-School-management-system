<?php

namespace App\Models;

use App\Enums\AttendanceStatus;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/** وضعیت یک اشتراک‌کننده در یک روز کورس (همان وضعیت‌های حاضری مکتب). */
#[Fillable(['course_session_id', 'course_participant_id', 'status', 'note'])]
class CourseAttendance extends Model
{
    protected $table = 'course_attendance';

    protected function casts(): array
    {
        return ['status' => AttendanceStatus::class];
    }

    public function session(): BelongsTo
    {
        return $this->belongsTo(CourseSession::class, 'course_session_id');
    }

    public function participant(): BelongsTo
    {
        return $this->belongsTo(CourseParticipant::class, 'course_participant_id');
    }
}
