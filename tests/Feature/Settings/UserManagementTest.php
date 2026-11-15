<?php

namespace Tests\Feature\Settings;

use App\Enums\Role;
use App\Models\ActivityLog;
use App\Models\Role as RoleModel;
use App\Models\User;
use Illuminate\Support\Facades\Hash;
use Tests\TestCase;

class UserManagementTest extends TestCase
{
    private User $admin;

    protected function setUp(): void
    {
        parent::setUp();
        $this->admin = $this->userWithRole(Role::Admin, ['username' => 'sysadmin']);
    }

    private function roleId(Role $role): int
    {
        return RoleModel::where('key', $role->value)->value('id');
    }

    public function test_admin_sees_user_list_and_can_filter(): void
    {
        $this->userWithRole(Role::Teacher, ['name' => 'معلم ریاضی', 'username' => 'math']);
        $this->userWithRole(Role::Accountant, ['name' => 'حسابدار مکتب', 'username' => 'acc']);

        $this->actingAs($this->admin)->get('/settings/users?role=teacher')
            ->assertOk()
            ->assertSee('معلم ریاضی')
            ->assertDontSee('حسابدار مکتب');
    }

    public function test_admin_creates_user_who_must_change_password(): void
    {
        $this->actingAs($this->admin)->post('/settings/users', [
            'name' => 'احمد کریمی',
            'username' => 'Ahmad.Karimi۱',
            'password' => 'initial-pass-1',
            'password_confirmation' => 'initial-pass-1',
            'is_active' => '1',
            'roles' => [$this->roleId(Role::Teacher), $this->roleId(Role::CourseManager)],
        ])->assertRedirect(route('settings.users.index'));

        $user = User::where('username', 'ahmad.karimi1')->firstOrFail();
        $this->assertTrue($user->must_change_password);
        $this->assertTrue($user->is_active);
        $this->assertTrue(Hash::check('initial-pass-1', $user->password));
        $this->assertEqualsCanonicalizing(['teacher', 'course_manager'], $user->roles->pluck('key')->all());

        $log = ActivityLog::where('action', 'user.created')->latest('id')->firstOrFail();
        $this->assertSame($this->admin->id, $log->user_id);
        $this->assertSame('ahmad.karimi1', $log->new_values['username']);
        $this->assertArrayNotHasKey('password', $log->new_values);
    }

    public function test_user_creation_is_validated(): void
    {
        $this->actingAs($this->admin)->post('/settings/users', [
            'name' => '',
            'username' => 'SYSADMIN',
            'password' => 'short',
            'password_confirmation' => 'other',
            'roles' => [],
        ])->assertSessionHasErrors(['name', 'username', 'password', 'roles']);

        $this->actingAs($this->admin)->post('/settings/users', [
            'name' => 'x',
            'username' => 'نام فارسی',
            'password' => 'initial-pass-1',
            'password_confirmation' => 'initial-pass-1',
            'roles' => [$this->roleId(Role::Teacher)],
        ])->assertSessionHasErrors(['username' => __('users.username_format')]);
    }

    public function test_admin_updates_roles_and_change_is_logged(): void
    {
        $user = $this->userWithRole(Role::Teacher, ['username' => 'math']);

        $this->actingAs($this->admin)->put("/settings/users/{$user->id}", [
            'name' => $user->name,
            'username' => 'math',
            'roles' => [$this->roleId(Role::Office)],
        ])->assertRedirect(route('settings.users.index'));

        $this->assertSame(['office'], $user->fresh()->roles->pluck('key')->all());

        $log = ActivityLog::where('action', 'user.updated')->latest('id')->firstOrFail();
        $this->assertSame(['teacher'], $log->old_values['roles']);
        $this->assertSame(['office'], $log->new_values['roles']);
    }

    public function test_admin_cannot_remove_own_admin_role(): void
    {
        $this->actingAs($this->admin)->put("/settings/users/{$this->admin->id}", [
            'name' => $this->admin->name,
            'username' => 'sysadmin',
            'roles' => [$this->roleId(Role::Teacher)],
        ])->assertSessionHasErrors(['roles' => __('users.cannot_remove_own_admin')]);

        $this->assertTrue($this->admin->fresh()->isAdmin());
    }

    public function test_admin_deactivates_and_reactivates_user(): void
    {
        $user = $this->userWithRole(Role::Teacher);

        $this->actingAs($this->admin)->patch("/settings/users/{$user->id}/toggle-active")->assertRedirect();
        $this->assertFalse($user->fresh()->is_active);
        $this->assertDatabaseHas('activity_logs', ['action' => 'user.deactivated', 'subject_id' => $user->id]);

        $this->actingAs($this->admin)->patch("/settings/users/{$user->id}/toggle-active");
        $this->assertTrue($user->fresh()->is_active);
    }

    public function test_admin_cannot_deactivate_self(): void
    {
        $this->actingAs($this->admin)->patch("/settings/users/{$this->admin->id}/toggle-active")->assertForbidden();
        $this->assertTrue($this->admin->fresh()->is_active);
    }

    public function test_admin_resets_forgotten_password(): void
    {
        $user = $this->userWithRole(Role::Teacher, ['username' => 'math']);

        $this->actingAs($this->admin)->put("/settings/users/{$user->id}/password", [
            'password' => 'fresh-pass-99',
            'password_confirmation' => 'fresh-pass-99',
        ])->assertRedirect(route('settings.users.edit', $user));

        $user->refresh();
        $this->assertTrue($user->must_change_password);
        $this->assertTrue(Hash::check('fresh-pass-99', $user->password));
        $this->assertDatabaseHas('activity_logs', ['action' => 'user.password_reset', 'subject_id' => $user->id]);

        auth()->logout();
        $this->post('/login', ['username' => 'math', 'password' => 'password'])->assertSessionHasErrors('username');
        $this->post('/login', ['username' => 'math', 'password' => 'fresh-pass-99'])->assertRedirect(route('dashboard'));
        $this->get('/')->assertRedirect(route('password.edit'));
    }

    public function test_principal_cannot_manage_users(): void
    {
        $principal = $this->userWithRole(Role::Principal);
        $user = $this->userWithRole(Role::Teacher);

        $this->actingAs($principal)->get("/settings/users/{$user->id}/edit")->assertForbidden();
        $this->actingAs($principal)->put("/settings/users/{$user->id}", ['name' => 'x', 'username' => 'xyz', 'roles' => [$this->roleId(Role::Admin)]])->assertForbidden();
        $this->actingAs($principal)->patch("/settings/users/{$user->id}/toggle-active")->assertForbidden();
        $this->actingAs($principal)->put("/settings/users/{$user->id}/password", ['password' => 'aaaaaaaa1', 'password_confirmation' => 'aaaaaaaa1'])->assertForbidden();

        $this->assertFalse($user->fresh()->isAdmin());
    }
}
