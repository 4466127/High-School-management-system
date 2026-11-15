<?php

namespace Tests;

use App\Enums\Role;
use App\Models\User;
use Illuminate\Foundation\Testing\TestCase as BaseTestCase;
use Tests\Concerns\UsesTestDatabase;

abstract class TestCase extends BaseTestCase
{
    use UsesTestDatabase;

    protected function userWithRole(Role $role, array $attributes = []): User
    {
        return User::factory()->withRole($role)->create($attributes);
    }
}
