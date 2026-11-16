<?php

namespace App\Http\Middleware;

use App\Models\Setting;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * بخش‌های اختیاری: ->middleware('feature:fees') یا 'feature:ledger'.
 * اگر در «مشخصات مکتب» خاموش باشد، صفحه وجود ندارد (۴۰۴) و در منو هم دیده نمی‌شود.
 */
class EnsureFeatureEnabled
{
    public const SETTINGS = ['fees' => 'fees_enabled', 'ledger' => 'school_ledger_enabled'];

    public function handle(Request $request, Closure $next, string $feature): Response
    {
        abort_unless(isset(self::SETTINGS[$feature]) && Setting::enabled(self::SETTINGS[$feature]), 404);

        return $next($request);
    }
}
