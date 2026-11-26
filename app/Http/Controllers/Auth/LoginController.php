<?php

namespace App\Http\Controllers\Auth;

use App\Http\Controllers\Controller;
use App\Http\Requests\Auth\LoginRequest;
use App\Services\ActivityLogger;
use App\Services\SetupService;
use App\Support\Digits;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Validation\ValidationException;
use Illuminate\View\View;

class LoginController extends Controller
{
    private const MAX_ATTEMPTS = 5;

    private const DECAY_SECONDS = 60;

    public function create(SetupService $setup): View|RedirectResponse
    {
        // نصب تازه: تا اولین مدیر سیستم ساخته نشده، صفحه‌ی راه‌اندازی
        if ($setup->pending()) {
            return redirect()->route('setup.create');
        }

        return view('auth.login');
    }

    public function store(LoginRequest $request, ActivityLogger $logger): RedirectResponse
    {
        $username = mb_strtolower(trim((string) $request->input('username')));
        $throttleKey = 'login|'.$username.'|'.$request->ip();

        if (RateLimiter::tooManyAttempts($throttleKey, self::MAX_ATTEMPTS)) {
            throw ValidationException::withMessages([
                'username' => __('auth.throttle', ['seconds' => Digits::toPersian(RateLimiter::availableIn($throttleKey))]),
            ]);
        }

        $credentials = ['username' => $username, 'password' => (string) $request->input('password')];

        if (! Auth::attempt($credentials, $request->boolean('remember'))) {
            RateLimiter::hit($throttleKey, self::DECAY_SECONDS);
            $logger->log('auth.failed', description: $username);

            throw ValidationException::withMessages(['username' => __('auth.failed')]);
        }

        $user = Auth::user();

        if (! $user->is_active) {
            Auth::guard('web')->logout();
            $request->session()->invalidate();
            $request->session()->regenerateToken();

            throw ValidationException::withMessages(['username' => __('auth.inactive')]);
        }

        RateLimiter::clear($throttleKey);
        $request->session()->regenerate();

        $user->forceFill(['last_login_at' => now()])->save();
        $logger->log('auth.login', $user, user: $user);

        return redirect()->intended(route('dashboard'));
    }

    public function destroy(Request $request, ActivityLogger $logger): RedirectResponse
    {
        $logger->log('auth.logout', $request->user());

        Auth::guard('web')->logout();
        $request->session()->invalidate();
        $request->session()->regenerateToken();

        return redirect()->route('login');
    }
}
