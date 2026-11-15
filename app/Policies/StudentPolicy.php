<?php

namespace App\Policies;

use App\Models\Student;
use App\Models\User;

/**
 * دیدن دانش‌آموز: students.view (معلم، حسابدار، مسئول کورس فقط مشخصات پایه).
 * تماس، آدرس، تذکره، سرپرستان و اسناد: فقط students.manage (مدیر، اداری).
 * محدود شدن معلم به «صنف خودش» در مرحله‌ی ۴ (پس از ساخت enrollments) به view اضافه می‌شود.
 */
class StudentPolicy
{
    public function viewAny(User $user): bool
    {
        return $user->hasPermission('students.view');
    }

    public function view(User $user, Student $student): bool
    {
        if ($student->trashed()) {
            return $user->hasPermission('students.manage');
        }

        return $user->hasPermission('students.view');
    }

    public function viewSensitive(User $user): bool
    {
        return $user->hasPermission('students.manage');
    }

    public function create(User $user): bool
    {
        return $user->hasPermission('students.manage');
    }

    public function update(User $user, Student $student): bool
    {
        return $user->hasPermission('students.manage') && ! $student->trashed();
    }

    public function delete(User $user, Student $student): bool
    {
        return $user->hasPermission('students.manage') && ! $student->trashed();
    }

    public function restore(User $user, Student $student): bool
    {
        return $user->hasPermission('students.manage') && $student->trashed();
    }
}
