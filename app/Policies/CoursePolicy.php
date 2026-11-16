<?php

namespace App\Policies;

use App\Enums\CourseStatus;
use App\Models\Course;
use App\Models\CourseGroup;
use App\Models\User;
use App\Services\AttendanceService;
use DateTimeInterface;

/**
 * کورس‌ها: مسئول کورس‌ها و مدیر همه را می‌بینند و مدیریت می‌کنند؛ حسابدار همه را (فقط دفتر مالی)؛
 * معلم فقط کورس‌هایی که در آن گروه دارد، و حاضری گروه خودش را فقط برای امروز ثبت می‌کند.
 */
class CoursePolicy
{
    public function viewAny(User $user): bool
    {
        return $user->hasPermission('courses.manage') || $user->hasPermission('courses.ledger') || $user->hasPermission('courses.attendance');
    }

    public function view(User $user, Course $course): bool
    {
        if ($user->hasPermission('courses.manage') || $user->hasPermission('courses.ledger')) {
            return true;
        }

        return $user->hasPermission('courses.attendance') && $user->staff_id !== null
            && $course->groups()->where('staff_id', $user->staff_id)->exists();
    }

    public function manage(User $user): bool
    {
        return $user->hasPermission('courses.manage');
    }

    public function ledger(User $user): bool
    {
        return $user->hasPermission('courses.ledger');
    }

    /** دیدن حاضری یک گروه */
    public function viewAttendance(User $user, CourseGroup $group): bool
    {
        return $user->hasPermission('courses.manage')
            || ($user->hasPermission('courses.attendance') && $user->staff_id !== null && $group->staff_id === $user->staff_id);
    }

    /** ثبت یا اصلاح حاضری یک گروه در یک روز */
    public function recordAttendance(User $user, CourseGroup $group, DateTimeInterface $date): bool
    {
        return $this->attendanceBlock($user, $group, $date) === null;
    }

    /** دلیل بسته بودن ثبت حاضری (null = باز) */
    public function attendanceBlock(User $user, CourseGroup $group, DateTimeInterface $date): ?string
    {
        $group->loadMissing('course');
        $day = $date->format('Y-m-d');
        $today = AttendanceService::today()->format('Y-m-d');

        return match (true) {
            ! $this->viewAttendance($user, $group) => __('courses.block_permission'),
            $group->course->status === CourseStatus::Cancelled => __('courses.block_cancelled'),
            $day > $today => __('courses.block_future'),
            $day < $group->course->start_date->format('Y-m-d') || ($group->course->end_date && $day > $group->course->end_date->format('Y-m-d')) => __('courses.block_outside'),
            ! $user->hasPermission('courses.manage') && $day !== $today => __('courses.block_only_today'),
            default => null,
        };
    }
}
