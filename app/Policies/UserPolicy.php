<?php

namespace App\Policies;

use App\Models\User;

/** مدیریت کاربران فقط برای کسی که مجوز users.manage دارد (پیش‌فرض: مدیر سیستم). */
class UserPolicy
{
    public function viewAny(User $actor): bool
    {
        return $actor->hasPermission('users.manage');
    }

    public function create(User $actor): bool
    {
        return $actor->hasPermission('users.manage');
    }

    public function update(User $actor, User $target): bool
    {
        return $actor->hasPermission('users.manage');
    }

    public function resetPassword(User $actor, User $target): bool
    {
        return $actor->hasPermission('users.manage');
    }

    /** هیچ‌کس نمی‌تواند حساب خودش را غیرفعال کند (تا سیستم بی‌مدیر نماند). */
    public function toggleActive(User $actor, User $target): bool
    {
        return $actor->hasPermission('users.manage') && ! $actor->is($target);
    }
}
