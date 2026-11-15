<?php

namespace Database\Seeders;

use App\Enums\Role as RoleKey;
use App\Models\Permission;
use App\Models\Role;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;

/**
 * نقش‌ها و مجوزها را با config/permissions.php هماهنگ می‌کند.
 * اجرای دوباره امن است: نام‌ها به‌روز و مجوزهای هر نقش مطابق config می‌شود؛
 * مجوزهای اضافی کاربران (permission_user) و نقش‌های کاربران دست نمی‌خورد.
 */
class RolePermissionSeeder extends Seeder
{
    public function run(): void
    {
        DB::transaction(function () {
            foreach (config('permissions.permissions') as $key => $group) {
                Permission::updateOrCreate(['key' => $key], ['name' => __('permissions.'.$key), 'group' => $group]);
            }

            foreach (RoleKey::cases() as $roleKey) {
                Role::updateOrCreate(['key' => $roleKey->value], ['name' => $roleKey->label()]);
            }

            $permissionIds = Permission::pluck('id', 'key');

            foreach (config('permissions.grants') as $roleKey => $keys) {
                Role::where('key', $roleKey)->firstOrFail()
                    ->permissions()
                    ->sync($permissionIds->only($keys)->values()->all());
            }
        });
    }
}
