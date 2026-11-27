<?php

namespace Tests\Feature\Auth;

use App\Enums\Role;
use App\Models\User;
use Illuminate\Support\Facades\Hash;
use Tests\TestCase;

class PasswordChangeTest extends TestCase
{
    public function test_user_who_must_change_password_is_forced_to_password_page(): void
    {
        $user = User::factory()->mustChangePassword()->withRole(Role::Principal)->create();

        $this->actingAs($user)->get('/')->assertRedirect(route('password.edit'));
        $this->actingAs($user)->get('/password')->assertOk()->assertSee('تغییر رمز');
    }

    public function test_user_changes_own_password(): void
    {
        $user = User::factory()->mustChangePassword()->withRole(Role::Principal)->create();

        $this->actingAs($user)->put('/password', [
            'current_password' => 'password',
            'password' => 'new-secret-123',
            'password_confirmation' => 'new-secret-123',
        ])->assertRedirect(route('dashboard'));

        $user->refresh();
        $this->assertTrue(Hash::check('new-secret-123', $user->password));
        $this->assertFalse($user->must_change_password);
        $this->assertDatabaseHas('activity_logs', ['action' => 'auth.password_changed', 'user_id' => $user->id]);
        $this->actingAs($user)->get('/')->assertOk();
    }

    public function test_wrong_current_password_is_rejected(): void
    {
        $user = $this->userWithRole(Role::Teacher);

        $this->actingAs($user)->put('/password', [
            'current_password' => 'not-it',
            'password' => 'new-secret-123',
            'password_confirmation' => 'new-secret-123',
        ])->assertSessionHasErrors('current_password');
    }

    public function test_new_password_must_differ_and_be_long_enough(): void
    {
        $user = $this->userWithRole(Role::Teacher);

        $this->actingAs($user)->put('/password', [
            'current_password' => 'password',
            'password' => 'password',
            'password_confirmation' => 'password',
        ])->assertSessionHasErrors('password');

        $this->actingAs($user)->put('/password', [
            'current_password' => 'password',
            'password' => 'short',
            'password_confirmation' => 'short',
        ])->assertSessionHasErrors('password');
    }
}
