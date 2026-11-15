<?php

namespace App\Policies;

use App\Models\Student;
use App\Models\User;

/**
 * دیدن دانش‌آموزان:
 *  - students.manage یا students.view_all (مدیر، اداره، حسابدار، مسئول کورس): همه، با مشخصات پایه؛
 *  - فقط students.view (معلم): دانش‌آموزان بخش‌هایی که نگران صنف یا معلم مضمون آن است (سال جاری).
 * تماس، آدرس، تذکره، سرپرستان و اسناد: فقط students.manage.
 */
class StudentPolicy
{
    public function viewAny(User $user): bool
    {
        return $user->hasPermission('students.view');
    }

    /** همه‌ی دانش‌آموزان را می‌بیند (بدون محدوده‌ی بخش)؟ */
    public function viewAll(User $user): bool
    {
        return $user->hasPermission('students.manage') || $user->hasPermission('students.view_all');
    }

    public function view(User $user, Student $student): bool
    {
        if ($student->trashed()) {
            return $user->hasPermission('students.manage');
        }
        if ($this->viewAll($user)) {
            return true;
        }

        return $user->hasPermission('students.view')
            && Student::whereKey($student->id)->inSections($user->taughtSectionIds())->exists();
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
