<?php

namespace App\Policies;

use App\Models\Section;
use App\Models\User;
use App\Services\AttendanceService;
use DateTimeInterface;

/**
 * حاضری دانش‌آموزان:
 * - مدیر، اداره (attendance.edit یا academics.manage): همه‌ی بخش‌ها، امروز و روزهای گذشته.
 * - معلم (attendance.take): فقط بخش‌های خودش (نگران صنف یا معلم مضمون) و فقط امروز.
 * - روز آینده و سال بسته‌شده: هیچ‌کس.
 * حاضری کارکنان: staff.manage.
 */
class AttendancePolicy
{
    public function viewAny(User $user): bool
    {
        return $user->hasPermission('attendance.take')
            || $user->hasPermission('attendance.edit')
            || $user->hasPermission('reports.academic');
    }

    public function viewSection(User $user, Section $section): bool
    {
        if ($user->hasPermission('attendance.edit') || $user->hasPermission('academics.manage')) {
            return true;
        }

        return $this->viewAny($user) && in_array($section->id, $user->taughtSectionIds(), true);
    }

    public function record(User $user, Section $section, DateTimeInterface $date): bool
    {
        $day = $date->format('Y-m-d');
        $today = AttendanceService::today()->format('Y-m-d');

        if ($day > $today || $section->academicYear?->isClosed()) {
            return false;
        }

        if ($user->hasPermission('attendance.edit')) {
            return $this->viewSection($user, $section);
        }

        return $day === $today
            && $user->hasPermission('attendance.take')
            && $this->viewSection($user, $section);
    }

    public function manageStaff(User $user): bool
    {
        return $user->hasPermission('staff.manage');
    }
}
