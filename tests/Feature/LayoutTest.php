<?php

namespace Tests\Feature;

use App\Enums\Role;
use Tests\TestCase;

/** اعلان‌های سبز/سرخ و منوی بازشونده. */
class LayoutTest extends TestCase
{
    public function test_success_and_error_messages_are_toasts(): void
    {
        $user = $this->userWithRole(Role::Office);

        $this->actingAs($user)->withSession(['success' => 'کار انجام شد'])->get('/')
            ->assertOk()->assertSee('app-toast app-toast-success', false)->assertSee('data-toast-ms="10000"', false)->assertSee('کار انجام شد');

        $this->actingAs($user)->withSession(['error' => 'ذخیره نشد چون …'])->get('/')
            ->assertOk()->assertSee('app-toast app-toast-danger', false)->assertSee('ذخیره نشد چون …');
    }

    public function test_validation_errors_show_a_red_toast_with_reasons(): void
    {
        $user = $this->userWithRole(Role::Office);
        $this->actingAs($user)->from('/students/create')->post('/students', [])->assertRedirect('/students/create');

        $this->actingAs($user)->get('/students/create')
            ->assertOk()->assertSee('app-toast app-toast-danger', false)->assertSee(__('app.form_has_errors'));
    }

    public function test_menu_groups_are_collapsible_and_current_group_is_open(): void
    {
        $html = $this->actingAs($this->userWithRole(Role::Principal))->get(route('attendance.daily.index'))->assertOk()->getContent();

        $this->assertStringContainsString('data-bs-target="#nav-attendance"', $html);
        $this->assertMatchesRegularExpression('~class="nav-sub collapse show" id="nav-attendance"~', $html);
        $this->assertMatchesRegularExpression('~class="nav-sub collapse" id="nav-exams"~', $html);
    }

    /** 1.8.1: موبایل — دکمه‌ی منو و پس‌زمینه‌ی منوی کشویی در هر صفحه */
    public function test_mobile_menu_toggle_is_rendered(): void
    {
        $this->actingAs($this->userWithRole(Role::Teacher))->get('/')->assertOk()
            ->assertSee('data-side-toggle', false)->assertSee('aria-controls="side-menu"', false)->assertSee('side-backdrop', false);
    }
}
