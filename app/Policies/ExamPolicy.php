<?php

namespace App\Policies;

use App\Enums\ExamStatus;
use App\Models\Exam;
use App\Models\Section;
use App\Models\Subject;
use App\Models\TeacherAssignment;
use App\Models\User;

/**
 * امتحانات و نمرات:
 * - exams.manage (مدیر): تعریف و قفل امتحان، نمره‌ی همه‌ی مضامین (حتی امتحان قفل‌شده)، نهایی‌کردن.
 * - marks.enter (معلم): فقط مضمونی که در همان بخش معلم آن است، و فقط وقتی امتحان «باز» است.
 * - reportcards.print: جدول نتایج و کارنامه؛ معلم فقط بخش‌های خودش، اداره همه.
 */
class ExamPolicy
{
    public function manage(User $user): bool
    {
        return $user->hasPermission('exams.manage');
    }

    public function viewMarks(User $user, Section $section, Subject $subject): bool
    {
        return $user->hasPermission('exams.manage') || $this->teaches($user, $section, $subject);
    }

    public function enterMarks(User $user, Exam $exam, Section $section, Subject $subject): bool
    {
        if ($exam->academic_year_id !== $section->academic_year_id
            || $section->academicYear?->isClosed()
            || $exam->status === ExamStatus::Draft) {
            return false;
        }

        if ($user->hasPermission('exams.manage')) {
            return true;
        }

        return $exam->status === ExamStatus::Open && $this->teaches($user, $section, $subject);
    }

    public function viewResults(User $user, Section $section): bool
    {
        if ($user->hasPermission('exams.manage')) {
            return true;
        }
        if (! $user->hasPermission('reportcards.print')) {
            return false;
        }

        return $user->hasPermission('students.view_all') || in_array($section->id, $user->taughtSectionIds(), true);
    }

    private function teaches(User $user, Section $section, Subject $subject): bool
    {
        return $user->hasPermission('marks.enter')
            && $user->staff_id
            && TeacherAssignment::where('section_id', $section->id)
                ->where('subject_id', $subject->id)
                ->where('staff_id', $user->staff_id)
                ->exists();
    }
}
