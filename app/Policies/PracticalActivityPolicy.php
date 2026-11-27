<?php

namespace App\Policies;

use App\Models\PracticalActivity;
use App\Models\Section;
use App\Models\Subject;
use App\Models\TeacherAssignment;
use App\Models\User;

/**
 * فعالیت عملی لابراتوار (مرحله‌ی ۱۸): مسئول لابراتوار و مدیر (labs.manage) همه را؛
 * معلم (labs.activity) فقط برای مضمون و بخشی که در «معلمان مضامین» به نام اوست پلان می‌سازد و فقط فعالیت خودش را ثبت می‌کند.
 * دیدن فهرست و جدول: labs.view.
 */
class PracticalActivityPolicy
{
    public function manage(User $user): bool
    {
        return $user->hasPermission('labs.manage');
    }

    public function viewAny(User $user): bool
    {
        return $this->manage($user) || $user->hasPermission('labs.view') || $user->hasPermission('labs.activity');
    }

    public function view(User $user, PracticalActivity $activity): bool
    {
        return $this->manage($user) || $user->hasPermission('labs.view') || $this->owns($user, $activity);
    }

    public function create(User $user, ?Section $section = null, ?Subject $subject = null): bool
    {
        if ($this->manage($user)) {
            return true;
        }
        if (! $user->hasPermission('labs.activity') || ! $user->staff_id) {
            return false;
        }

        return ! $section || ! $subject
            || TeacherAssignment::where('section_id', $section->id)->where('subject_id', $subject->id)->where('staff_id', $user->staff_id)->exists();
    }

    public function update(User $user, PracticalActivity $activity): bool
    {
        return $this->manage($user) || $this->owns($user, $activity);
    }

    private function owns(User $user, PracticalActivity $activity): bool
    {
        return $user->hasPermission('labs.activity') && $user->staff_id && (int) $activity->staff_id === (int) $user->staff_id;
    }
}
