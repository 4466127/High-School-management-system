<?php

namespace Tests\Feature\Authorization;

use App\Enums\Role;
use App\Models\Permission;
use App\Models\Setting;
use App\Models\User;
use App\Support\Menu;
use Illuminate\Support\Facades\Gate;
use PHPUnit\Framework\Attributes\DataProvider;
use Tests\TestCase;

class RoleAccessTest extends TestCase
{
    /** نقش => [کلیدهای منوی قابل دید، کلیدهای زیرمنوی تنظیمات] — مطابق ARCHITECTURE.md بخش ۶ (مالی خاموش) */
    public static function expectedMenus(): array
    {
        return [
            'admin' => [Role::Admin, ['dashboard', 'students', 'staff', 'academics', 'attendance', 'timetable', 'teaching', 'exams', 'courses', 'reports', 'library', 'announcements', 'letters', 'settings'], ['school', 'users', 'activity_logs', 'import', 'backups', 'license']],
            'principal' => [Role::Principal, ['dashboard', 'students', 'staff', 'academics', 'attendance', 'timetable', 'teaching', 'exams', 'courses', 'reports', 'library', 'announcements', 'letters', 'settings'], ['school', 'import']],
            'office' => [Role::Office, ['dashboard', 'students', 'staff', 'academics', 'attendance', 'timetable', 'teaching', 'exams', 'reports', 'library', 'announcements', 'letters', 'settings'], ['import']],
            'teacher' => [Role::Teacher, ['dashboard', 'students', 'academics', 'attendance', 'timetable', 'teaching', 'exams', 'courses', 'reports', 'library', 'announcements'], []],
            'accountant' => [Role::Accountant, ['dashboard', 'students', 'courses', 'reports', 'announcements'], []],
            'course_manager' => [Role::CourseManager, ['dashboard', 'students', 'courses', 'reports', 'announcements'], []],
            'librarian' => [Role::Librarian, ['dashboard', 'students', 'library', 'announcements', 'settings'], ['import']],
        ];
    }

    #[DataProvider('expectedMenus')]
    public function test_each_role_sees_only_its_menu(Role $role, array $menuKeys, array $settingsKeys): void
    {
        $user = $this->userWithRole($role);
        $menu = app(Menu::class)->for($user);

        $this->assertSame($menuKeys, array_column($menu, 'key'));

        $settings = collect($menu)->firstWhere('key', 'settings');
        $this->assertSame($settingsKeys, $settings ? array_column($settings['children'], 'key') : []);
    }

    public function test_attendance_submenu_per_role(): void
    {
        $expected = [
            'admin' => [Role::Admin, ['attendance_daily', 'attendance_monthly', 'attendance_absentees', 'attendance_staff']],
            'principal' => [Role::Principal, ['attendance_daily', 'attendance_monthly', 'attendance_absentees', 'attendance_staff']],
            'office' => [Role::Office, ['attendance_daily', 'attendance_monthly', 'attendance_absentees', 'attendance_staff']],
            'teacher' => [Role::Teacher, ['attendance_daily', 'attendance_monthly', 'attendance_absentees']],
        ];

        foreach ($expected as $name => [$role, $keys]) {
            $attendance = collect(app(Menu::class)->for($this->userWithRole($role)))->firstWhere('key', 'attendance');
            $this->assertSame($keys, array_column($attendance['children'], 'key'), $name);
        }
    }

    public function test_exams_submenu_per_role(): void
    {
        $expected = [
            'admin' => [Role::Admin, ['exams_marks', 'exams_results', 'exams_reportcards', 'exams_transcripts', 'exams_reexam', 'exams_year_review', 'exams_manage', 'exams_templates']],
            'principal' => [Role::Principal, ['exams_marks', 'exams_results', 'exams_reportcards', 'exams_transcripts', 'exams_reexam', 'exams_year_review', 'exams_manage', 'exams_templates']],
            'office' => [Role::Office, ['exams_results', 'exams_reportcards', 'exams_transcripts']],
            'teacher' => [Role::Teacher, ['exams_marks', 'exams_results', 'exams_reportcards', 'exams_transcripts']],
        ];

        foreach ($expected as $name => [$role, $keys]) {
            $exams = collect(app(Menu::class)->for($this->userWithRole($role)))->firstWhere('key', 'exams');
            $this->assertSame($keys, array_column($exams['children'], 'key'), $name);
        }
    }

    public function test_courses_submenu_per_role(): void
    {
        $expected = [
            'admin' => [Role::Admin, ['courses_list', 'courses_attendance', 'courses_ledger']],
            'principal' => [Role::Principal, ['courses_list', 'courses_attendance', 'courses_ledger']],
            'teacher' => [Role::Teacher, ['courses_list', 'courses_attendance']],
            'accountant' => [Role::Accountant, ['courses_list', 'courses_ledger']],
            'course_manager' => [Role::CourseManager, ['courses_list', 'courses_attendance', 'courses_ledger']],
        ];

        foreach ($expected as $name => [$role, $keys]) {
            $courses = collect(app(Menu::class)->for($this->userWithRole($role)))->firstWhere('key', 'courses');
            $this->assertSame($keys, array_column($courses['children'], 'key'), $name);
        }
    }

    #[DataProvider('expectedMenus')]
    public function test_each_role_reaches_dashboard_and_sees_its_menu_labels(Role $role, array $menuKeys, array $settingsKeys): void
    {
        $response = $this->actingAs($this->userWithRole($role))->get('/');

        $response->assertOk()->assertSee($role->label());

        // فقط داخل منوی کنار بررسی شود (متن داشبورد هم این واژه‌ها را دارد)
        preg_match('~<aside class="side".*?</aside>~s', $response->getContent(), $m);
        $sidebar = $m[0] ?? '';
        $this->assertNotSame('', $sidebar);
        // فقط برچسب‌های سطح اول (زیرمنوها مثل «حاضری کورس» واژه‌ی گروه‌های دیگر را دارند و جدا تست می‌شوند)
        $sidebar = preg_replace('~<ul class="nav-sub.*?</ul>~s', '', $sidebar);

        foreach (array_keys(__('menu')) as $key) {
            if (in_array($key, ['school', 'users', 'activity_logs', 'backups', 'import', 'sections', 'years', 'grades', 'promotion',
                'attendance_daily', 'attendance_monthly', 'attendance_absentees', 'attendance_staff',
                'exams_marks', 'exams_results', 'exams_reportcards', 'exams_reexam', 'exams_manage',
                'courses_list', 'courses_attendance', 'courses_ledger'], true)) {
                continue;
            }
            in_array($key, $menuKeys, true)
                ? $this->assertStringContainsString(__('menu.'.$key), $sidebar, "Role {$role->value} should see {$key}")
                : $this->assertStringNotContainsString(__('menu.'.$key), $sidebar, "Role {$role->value} must not see {$key}");
        }
    }

    public function test_finance_menu_appears_only_when_enabled_and_permitted(): void
    {
        $accountant = $this->userWithRole(Role::Accountant);
        $teacher = $this->userWithRole(Role::Teacher);

        $this->assertNotContains('finance', array_column(app(Menu::class)->for($accountant), 'key'));

        Setting::put('fees_enabled', '1');

        $this->assertContains('finance', array_column(app(Menu::class)->for($accountant), 'key'));
        $this->assertNotContains('finance', array_column(app(Menu::class)->for($teacher), 'key'));
    }

    public static function restrictedRoutes(): array
    {
        return [
            'users list' => ['GET', '/settings/users'],
            'create user form' => ['GET', '/settings/users/create'],
            'store user' => ['POST', '/settings/users'],
            'activity logs' => ['GET', '/settings/activity-logs'],
        ];
    }

    #[DataProvider('restrictedRoutes')]
    public function test_only_admin_reaches_system_pages(string $method, string $uri): void
    {
        foreach ([Role::Principal, Role::Office, Role::Teacher, Role::Accountant, Role::CourseManager] as $role) {
            $this->actingAs($this->userWithRole($role))
                ->call($method, $uri)
                ->assertForbidden();
        }

        if ($method === 'GET') {
            $this->actingAs($this->userWithRole(Role::Admin))->get($uri)->assertOk();
        }
    }

    public function test_forbidden_page_is_persian(): void
    {
        $this->actingAs($this->userWithRole(Role::Teacher))
            ->get('/settings/users')
            ->assertForbidden()
            ->assertSee('دسترسی ندارید');
    }

    public function test_user_without_role_cannot_open_dashboard(): void
    {
        $this->actingAs(User::factory()->create())->get('/')->assertForbidden();
    }

    public function test_gates_follow_role_grants(): void
    {
        $teacher = $this->userWithRole(Role::Teacher);
        $accountant = $this->userWithRole(Role::Accountant);
        $admin = $this->userWithRole(Role::Admin);

        $this->assertTrue(Gate::forUser($teacher)->allows('marks.enter'));
        $this->assertTrue(Gate::forUser($teacher)->allows('attendance.take'));
        $this->assertFalse(Gate::forUser($teacher)->allows('finance.view'), 'Teacher must not see confidential finance data');
        $this->assertFalse(Gate::forUser($teacher)->allows('students.manage'));

        $this->assertTrue(Gate::forUser($accountant)->allows('finance.manage'));
        $this->assertFalse(Gate::forUser($accountant)->allows('marks.enter'));

        foreach (array_keys(config('permissions.permissions')) as $permission) {
            $this->assertTrue(Gate::forUser($admin)->allows($permission), "Admin lacks {$permission}");
        }
    }

    public function test_extra_permission_can_be_granted_to_one_user(): void
    {
        $teacher = $this->userWithRole(Role::Teacher);
        $this->assertFalse($teacher->hasPermission('reports.finance'));

        $teacher->permissions()->attach(Permission::where('key', 'reports.finance')->value('id'));
        $teacher->flushPermissionCache();

        $this->assertTrue($teacher->hasPermission('reports.finance'));
        $this->assertFalse($this->userWithRole(Role::Teacher)->hasPermission('reports.finance'));
    }

    public function test_user_with_multiple_roles_gets_union_of_permissions(): void
    {
        $user = $this->userWithRole(Role::Teacher);
        $user->roles()->attach(\App\Models\Role::where('key', Role::CourseManager->value)->value('id'));
        $user->flushPermissionCache();

        $this->assertTrue($user->hasPermission('marks.enter'));
        $this->assertTrue($user->hasPermission('courses.ledger'));
    }
}
