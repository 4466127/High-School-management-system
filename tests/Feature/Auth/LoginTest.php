<?php

namespace Tests\Feature\Auth;

use App\Enums\Role;
use App\Models\ActivityLog;
use App\Models\User;
use Tests\TestCase;

class LoginTest extends TestCase
{
    public function test_login_page_is_persian_rtl_and_fully_local(): void
    {
        $response = $this->get('/login');

        $response->assertOk()
            ->assertSee('dir="rtl"', false)
            ->assertSee('ورود به سیستم')
            ->assertSee('name="username"', false)
            ->assertDontSee('name="email"', false)
            ->assertDontSee('cdn', false)
            ->assertDontSee('googleapis', false)
            ->assertSee('assets/vendor/bootstrap/css/bootstrap.rtl.min.css', false);

        $this->assertStringContainsString("script-src 'self'", $response->headers->get('Content-Security-Policy'));
        $response->assertHeader('X-Frame-Options', 'SAMEORIGIN');
    }

    public function test_guest_is_redirected_to_login(): void
    {
        $this->get('/')->assertRedirect('/login');
        $this->get('/settings/users')->assertRedirect('/login');
    }

    public function test_user_logs_in_with_username_and_password(): void
    {
        $user = $this->userWithRole(Role::Teacher, ['username' => 'karim']);

        $this->post('/login', ['username' => 'karim', 'password' => 'password'])
            ->assertRedirect(route('dashboard'));

        $this->assertAuthenticatedAs($user);
        $this->assertNotNull($user->fresh()->last_login_at);
        $this->assertDatabaseHas('activity_logs', ['action' => 'auth.login', 'user_id' => $user->id]);
    }

    public function test_username_is_case_insensitive(): void
    {
        $user = $this->userWithRole(Role::Teacher, ['username' => 'karim']);

        $this->post('/login', ['username' => ' KARIM ', 'password' => 'password']);

        $this->assertAuthenticatedAs($user);
    }

    public function test_persian_digits_in_credentials_are_normalized(): void
    {
        $user = $this->userWithRole(Role::Teacher, ['username' => 'teacher12', 'password' => '12345678']);

        $this->post('/login', ['username' => 'teacher۱۲', 'password' => '۱۲۳۴۵۶۷۸']);

        $this->assertAuthenticatedAs($user);
    }

    public function test_wrong_password_is_rejected_and_logged(): void
    {
        $this->userWithRole(Role::Teacher, ['username' => 'karim']);

        $this->from('/login')->post('/login', ['username' => 'karim', 'password' => 'wrong-password'])
            ->assertRedirect('/login')
            ->assertSessionHasErrors(['username' => __('auth.failed')]);

        $this->assertGuest();
        $this->assertSame('karim', ActivityLog::where('action', 'auth.failed')->latest('id')->value('description'));
    }

    public function test_inactive_user_cannot_log_in(): void
    {
        User::factory()->inactive()->withRole(Role::Teacher)->create(['username' => 'karim']);

        $this->post('/login', ['username' => 'karim', 'password' => 'password'])
            ->assertSessionHasErrors(['username' => __('auth.inactive')]);

        $this->assertGuest();
    }

    public function test_login_is_throttled_after_five_failures(): void
    {
        $this->userWithRole(Role::Teacher, ['username' => 'karim']);

        for ($i = 0; $i < 5; $i++) {
            $this->post('/login', ['username' => 'karim', 'password' => 'wrong']);
        }

        $this->post('/login', ['username' => 'karim', 'password' => 'password'])
            ->assertSessionHasErrors('username');

        $this->assertGuest();
        $this->assertStringContainsString('تلاش‌های زیاد', session('errors')->first('username'));
    }

    public function test_user_deactivated_during_session_is_logged_out(): void
    {
        $user = $this->userWithRole(Role::Teacher);
        $this->actingAs($user)->get('/')->assertOk();

        $user->update(['is_active' => false]);

        $this->actingAs($user->fresh())->get('/')
            ->assertRedirect('/login')
            ->assertSessionHasErrors(['username' => __('auth.inactive')]);
        $this->assertGuest();
    }

    public function test_logout_ends_session_and_is_logged(): void
    {
        $user = $this->userWithRole(Role::Teacher);

        $this->actingAs($user)->post('/logout')->assertRedirect('/login');

        $this->assertGuest();
        $this->assertDatabaseHas('activity_logs', ['action' => 'auth.logout', 'user_id' => $user->id]);
    }

    public function test_logged_in_user_visiting_login_goes_to_dashboard(): void
    {
        $this->actingAs($this->userWithRole(Role::Teacher))->get('/login')->assertRedirect(route('dashboard'));
    }
}
