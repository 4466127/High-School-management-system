<?php

namespace Database\Seeders;

use App\Enums\Role as RoleKey;
use App\Models\Role;
use App\Models\User;
use Illuminate\Database\Seeder;
use Illuminate\Support\Str;

/**
 * اولین حساب «مدیر سیستم» — فقط وقتی هیچ مدیر سیستمی وجود ندارد.
 * رمز تصادفی ساخته و یک بار در خروجی نشان داده می‌شود؛ در اولین ورود باید عوض شود.
 */
class AdminUserSeeder extends Seeder
{
    public function run(): void
    {
        $adminRole = Role::where('key', RoleKey::Admin->value)->firstOrFail();

        if ($adminRole->users()->exists()) {
            $this->command?->info('Admin user already exists; skipped.');

            return;
        }

        $username = User::where('username', 'admin')->exists() ? 'admin'.random_int(100, 999) : 'admin';
        $password = Str::password(12, symbols: false);

        $user = User::create([
            'name' => __('roles.admin'),
            'username' => $username,
            'password' => $password,
            'is_active' => true,
            'must_change_password' => true,
        ]);
        $user->roles()->attach($adminRole);

        $this->command?->warn("Admin user created. Username: {$username}  Password: {$password}  (must be changed at first login)");
    }
}
