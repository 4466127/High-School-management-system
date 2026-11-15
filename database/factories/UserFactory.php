<?php

namespace Database\Factories;

use App\Enums\Role as RoleKey;
use App\Models\Role;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;

/**
 * @extends Factory<User>
 */
class UserFactory extends Factory
{
    protected static ?string $password;

    public function definition(): array
    {
        return [
            'name' => fake()->name(),
            'username' => 'user'.Str::lower(Str::random(8)),
            'password' => static::$password ??= Hash::make('password'),
            'is_active' => true,
            'must_change_password' => false,
            'remember_token' => Str::random(10),
        ];
    }

    public function inactive(): static
    {
        return $this->state(fn () => ['is_active' => false]);
    }

    public function mustChangePassword(): static
    {
        return $this->state(fn () => ['must_change_password' => true]);
    }

    /** نقش‌ها باید قبلاً seed شده باشند (RolePermissionSeeder). */
    public function withRole(RoleKey $role): static
    {
        return $this->afterCreating(fn (User $user) => $user->roles()->attach(Role::where('key', $role->value)->firstOrFail()));
    }
}
