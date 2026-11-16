<?php

namespace App\Http\Controllers;

use App\Services\DashboardService;
use App\Support\Menu;
use Illuminate\Http\Request;
use Illuminate\View\View;

/** داشبورد هر نقش: آمار و کارهای امروز به اندازه‌ی مجوزهای کاربر، و دسترسی سریع. */
class DashboardController extends Controller
{
    public function __invoke(Request $request, Menu $menu, DashboardService $dashboard): View
    {
        $user = $request->user()->loadMissing('roles');

        $tiles = collect($menu->for($user))
            ->flatMap(fn (array $item) => $item['children'] === [] ? [$item] : $item['children'])
            ->reject(fn (array $item) => $item['key'] === 'dashboard')
            ->values();

        return view('dashboard', ['user' => $user, 'tiles' => $tiles] + $dashboard->for($user));
    }
}
