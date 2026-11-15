<?php

namespace App\Http\Controllers;

use App\Support\Menu;
use Illuminate\Http\Request;
use Illuminate\View\View;

/** داشبورد مرحله‌ی ۲: خوش‌آمد و دسترسی سریع به بخش‌های مجاز. آمار در مرحله‌ی ۹ اضافه می‌شود. */
class DashboardController extends Controller
{
    public function __invoke(Request $request, Menu $menu): View
    {
        $user = $request->user()->loadMissing('roles');

        $tiles = collect($menu->for($user))
            ->flatMap(fn (array $item) => $item['children'] === [] ? [$item] : $item['children'])
            ->reject(fn (array $item) => $item['key'] === 'dashboard')
            ->values();

        return view('dashboard', ['user' => $user, 'tiles' => $tiles]);
    }
}
