<?php

namespace Tests\Feature\Security;

use App\Enums\Role;
use App\Models\Setting;
use App\Models\User;
use Carbon\CarbonImmutable;
use Database\Seeders\DatabaseSeeder;
use Database\Seeders\DemoAcademicSeeder;
use Database\Seeders\DemoAttendanceSeeder;
use Database\Seeders\DemoCourseSeeder;
use Database\Seeders\DemoExamSeeder;
use Database\Seeders\DemoFinanceSeeder;
use Database\Seeders\DemoLabSeeder;
use Database\Seeders\DemoLibrarySeeder;
use Database\Seeders\DemoRecordsSeeder;
use Database\Seeders\DemoSchoolSeeder;
use Database\Seeders\DemoTeachingSeeder;
use Database\Seeders\DemoTimetableSeeder;
use Database\Seeders\DemoUserSeeder;
use Illuminate\Contracts\Routing\UrlRoutable;
use Illuminate\Routing\Route;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Route as RouteFacade;
use Tests\TestCase;

/**
 * مرحله‌ی ۱۹ — بازبینی امنیت: همه‌ی مسیرها با داده‌ی نمونه‌ی کامل.
 * ۱) کاربر بی‌نقش (وارد شده ولی بی مجوز) هیچ مسیری جز فهرست مجاز را باز نمی‌کند — نه GET و نه POST/PUT/DELETE
 *    (۹۲ مسیر مجوز را در کنترلر با Gate/Policy می‌گیرند، نه با can: روی مسیر؛ این تست همه را می‌آزماید).
 * ۲) مهمان (وارد نشده) به صفحه‌ی ورود فرستاده می‌شود.
 * ۳) هر نقش همه‌ی صفحه‌های GET را باز می‌کند بی خطای ۵۰۰ (یا ۲۰۰ یا ۴۰۳/۴۰۴/ریدایرکت).
 */
class RouteSweepTest extends TestCase
{
    /** مسیرهایی که هر کاربر وارد شده (حتی بی‌نقش) باز می‌کند */
    private const OPEN_TO_ANY_USER = ['logout', 'password.edit', 'password.update', 'school.logo', 'announcements.index', 'announcements.show', 'announcements.attachment'];

    /** مسیرهای عمومی (بی ورود) */
    private const PUBLIC = ['login', 'login.store', 'setup.create', 'setup.store', 'school.logo'];

    protected function setUp(): void
    {
        parent::setUp();
        Carbon::setTestNow('2026-11-16 09:00:00');
        CarbonImmutable::setTestNow('2026-11-16 09:00:00');
        $this->seed(DatabaseSeeder::BASE);
        $this->seed([DemoUserSeeder::class, DemoSchoolSeeder::class, DemoAcademicSeeder::class, DemoAttendanceSeeder::class, DemoExamSeeder::class,
            DemoFinanceSeeder::class, DemoCourseSeeder::class, DemoRecordsSeeder::class, DemoTimetableSeeder::class, DemoTeachingSeeder::class,
            DemoLibrarySeeder::class, DemoLabSeeder::class]);
        Setting::put('fees_enabled', '1');
        Setting::put('school_ledger_enabled', '1');
    }

    protected function tearDown(): void
    {
        Carbon::setTestNow();
        CarbonImmutable::setTestNow();
        parent::tearDown();
    }

    /** @return list<array{route: Route, method: string, uri: string}> */
    private function routes(): array
    {
        $list = [];
        foreach (RouteFacade::getRoutes()->getRoutes() as $route) {
            $name = (string) $route->getName();
            if ($route->uri() === 'up' || str_starts_with($route->uri(), '_') || str_starts_with($name, 'storage.')) {
                continue;
            }
            $uri = $this->fill($route);
            if ($uri === null) {
                continue;
            }
            $method = collect($route->methods())->reject(fn ($m) => $m === 'HEAD')->first();
            $list[] = ['route' => $route, 'method' => $method, 'uri' => $uri];
        }

        return $list;
    }

    /** پارامترهای مسیر با اولین رکورد واقعی همان مدل (یا مقدار معتبر برای پارامترهای ساده) */
    private function fill(Route $route): ?string
    {
        $values = [];
        $bound = [];
        foreach ($route->signatureParameters(['subClass' => UrlRoutable::class]) as $param) {
            $class = $param->getType()?->getName();
            $bound[$param->getName()] = $class;
        }
        foreach ($route->parameterNames() as $name) {
            if (isset($bound[$name])) {
                $class = $bound[$name];
                $model = method_exists($class, 'withTrashed') ? $class::withTrashed()->first() : $class::query()->first();
                if (! $model) {
                    return null;
                }
                $values[$name] = $model->getRouteKey();
            } else {
                $values[$name] = match ($name) {
                    'type' => 'students',
                    'direction' => 'outgoing',
                    'backup', 'file' => 'none.zip',
                    default => '1',
                };
            }
        }

        return '/'.ltrim(preg_replace_callback('/\{(\w+)\??\}/', fn ($m) => (string) ($values[$m[1]] ?? ''), $route->uri()), '/');
    }

    public function test_user_without_roles_is_forbidden_everywhere(): void
    {
        $nobody = User::factory()->create(['must_change_password' => false]);
        $leaks = [];
        foreach ($this->routes() as ['route' => $route, 'method' => $method, 'uri' => $uri]) {
            if (in_array($route->getName(), self::OPEN_TO_ANY_USER, true) || in_array($route->getName(), self::PUBLIC, true)) {
                continue;
            }
            $status = $this->actingAs($nobody)->call($method, $uri)->getStatusCode();
            // ۴۰۳ = بسته؛ ۴۰۴ = ویژگی خاموش یا فایل نیست. ۲۰۰/۳۰۲/۴۲۲ یعنی کنترلر پیش از بررسی مجوز کار کرد.
            if (! in_array($status, [403, 404], true)) {
                $leaks[] = "{$method} {$uri} ({$route->getName()}) → {$status}";
            }
        }
        $this->assertSame([], $leaks, "Routes reachable without any permission:\n".implode("\n", $leaks));
    }

    public function test_guest_is_sent_to_login(): void
    {
        $open = [];
        foreach ($this->routes() as ['route' => $route, 'method' => $method, 'uri' => $uri]) {
            if (in_array($route->getName(), self::PUBLIC, true)) {
                continue;
            }
            $response = $this->call($method, $uri);
            if (! ($response->isRedirect() && str_contains((string) $response->headers->get('Location'), '/login'))) {
                $open[] = "{$method} {$uri} → {$response->getStatusCode()}";
            }
        }
        $this->assertSame([], $open, "Routes open to guests:\n".implode("\n", $open));
    }

    public function test_every_role_opens_every_page_without_server_error(): void
    {
        $errors = [];
        $gets = array_filter($this->routes(), fn ($r) => $r['method'] === 'GET' && $r['route']->getName() !== 'logout');
        foreach (Role::cases() as $role) {
            $user = User::where('username', 'demo.'.str_replace('_', '-', $role->value))->firstOrFail();
            foreach ($gets as ['route' => $route, 'uri' => $uri]) {
                $response = $this->actingAs($user)->get($uri);
                if ($response->getStatusCode() >= 500) {
                    $errors[] = "{$role->value}: GET {$uri} → {$response->getStatusCode()} ".mb_substr((string) ($response->exception?->getMessage() ?? ''), 0, 160);
                }
            }
        }
        $this->assertSame([], $errors, "Server errors:\n".implode("\n", $errors));
    }
}
