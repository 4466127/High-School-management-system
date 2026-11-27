<?php

namespace Database\Seeders;

use App\Enums\Role as RoleKey;
use App\Models\Role;
use App\Models\User;
use Illuminate\Database\Seeder;
use RuntimeException;

/**
 * فقط برای توسعه: یک کاربر ساختگی برای هر نقش، رمز همه «password».
 * روی سرور مکتب (APP_ENV=production) اجرا نمی‌شود.
 */
class DemoUserSeeder extends Seeder
{
    public const PASSWORD = 'password';

    public function run(): void
    {
        if (app()->isProduction()) {
            throw new RuntimeException('DemoUserSeeder must not run in production.');
        }

        foreach (RoleKey::cases() as $roleKey) {
            $user = User::firstOrCreate(
                ['username' => 'demo.'.str_replace('_', '-', $roleKey->value)],
                [
                    'name' => 'آزمایشی — '.$roleKey->label(),
                    'password' => self::PASSWORD,
                    'is_active' => true,
                    'must_change_password' => false,
                ],
            );
            $user->roles()->syncWithoutDetaching([Role::where('key', $roleKey->value)->value('id')]);
        }

        $this->command?->info('Demo users: demo.admin, demo.principal, demo.office, demo.teacher, demo.accountant, demo.course-manager, demo.librarian, demo.lab-manager (password: '.self::PASSWORD.')');
    }
}
