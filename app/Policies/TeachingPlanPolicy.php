<?php

namespace App\Policies;

use App\Models\Section;
use App\Models\Subject;
use App\Models\TeacherAssignment;
use App\Models\TeachingPlan;
use App\Models\User;

/**
 * پلان تدریس (مرحله‌ی ۱۶): مدیر (teaching.manage) همه را می‌بیند، منظور یا برمی‌گرداند و می‌تواند ویرایش کند؛
 * معلم (teaching.plan) فقط پلان مضمون و بخشی که در «معلمان مضامین» به نام اوست.
 */
class TeachingPlanPolicy
{
    public function review(User $user): bool
    {
        return $user->hasPermission('teaching.manage');
    }

    public function open(User $user, Section $section, Subject $subject): bool
    {
        return $this->review($user) || ($user->hasPermission('teaching.plan') && $user->staff_id
            && TeacherAssignment::where('section_id', $section->id)->where('subject_id', $subject->id)->where('staff_id', $user->staff_id)->exists());
    }

    public function view(User $user, TeachingPlan $plan): bool
    {
        return $this->update($user, $plan);
    }

    public function update(User $user, TeachingPlan $plan): bool
    {
        return $this->review($user) || ($user->hasPermission('teaching.plan') && $user->staff_id && (int) $plan->staff_id === (int) $user->staff_id);
    }
}
