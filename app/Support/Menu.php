<?php

namespace App\Support;

use App\Models\Setting;
use App\Models\User;
use Illuminate\Support\Facades\Route;

/** ساخت منوی اصلی براساس مجوزهای کاربر (config/menu.php). */
class Menu
{
    /** @return list<array{key:string,label:string,icon:string,url:?string,active:bool,children:list<array>}> */
    public function for(User $user): array
    {
        return $this->build(config('menu', []), $user);
    }

    private function build(array $items, User $user): array
    {
        $result = [];

        foreach ($items as $item) {
            if (! $this->allowed($item, $user)) {
                continue;
            }

            $children = $this->build($item['children'] ?? [], $user);
            if (isset($item['children']) && $children === []) {
                continue;
            }

            $route = $item['route'] ?? null;
            $available = $route !== null && Route::has($route);

            $result[] = [
                'key' => $item['key'],
                'label' => __('menu.'.$item['key']),
                'icon' => $item['icon'] ?? 'fa-circle',
                'url' => $available ? route($route) : null,
                'active' => $available && request()->routeIs($this->routePattern($route))
                    || collect($children)->contains('active', true),
                'children' => $children,
            ];
        }

        return $result;
    }

    private function allowed(array $item, User $user): bool
    {
        if (isset($item['feature']) && ! $this->featureEnabled($item['feature'])) {
            return false;
        }

        foreach ($item['can'] ?? [] as $permission) {
            if ($user->hasPermission($permission)) {
                return true;
            }
        }

        return false;
    }

    private function featureEnabled(string $feature): bool
    {
        return match ($feature) {
            'finance' => Setting::enabled('fees_enabled') || Setting::enabled('school_ledger_enabled'),
            'fees' => Setting::enabled('fees_enabled'),
            'ledger' => Setting::enabled('school_ledger_enabled'),
            default => true,
        };
    }

    /** settings.users.index ← settings.users.* (صفحه‌های ساخت و ویرایش هم فعال نشان داده شوند) */
    private function routePattern(string $route): string
    {
        return str_ends_with($route, '.index') ? substr($route, 0, -5).'*' : $route;
    }
}
