<?php

namespace App\Policies;

use App\Models\Section;
use App\Models\User;

/**
 * بخش‌ها: academics.manage (مدیر، اداره) همه؛ معلم (academics.view) فقط بخش‌هایی که نگران صنف یا معلم مضمون آن است.
 */
class SectionPolicy
{
    public function viewAny(User $user): bool
    {
        return $user->hasPermission('academics.view') || $user->hasPermission('academics.manage');
    }

    public function view(User $user, Section $section): bool
    {
        return $user->hasPermission('academics.manage')
            || ($user->hasPermission('academics.view') && in_array($section->id, $user->taughtSectionIds(), true));
    }

    public function manage(User $user): bool
    {
        return $user->hasPermission('academics.manage');
    }
}
