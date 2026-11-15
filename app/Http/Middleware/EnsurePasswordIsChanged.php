<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/** کاربری که رمزش را مدیر ساخته یا نو کرده، پیش از هر کار باید رمز خودش را بگذارد. */
class EnsurePasswordIsChanged
{
    public function handle(Request $request, Closure $next): Response
    {
        if ($request->user()?->must_change_password) {
            return redirect()->route('password.edit')->with('warning', __('auth.must_change_password'));
        }

        return $next($request);
    }
}
