<?php

namespace App\Http\Controllers\Auth;

use App\Http\Controllers\Controller;
use App\Http\Requests\Auth\UpdatePasswordRequest;
use App\Services\ActivityLogger;
use Illuminate\Http\RedirectResponse;
use Illuminate\View\View;

/** تغییر رمز توسط خود کاربر (اجباری بعد از ساخت حساب یا نو کردن رمز توسط مدیر). */
class PasswordController extends Controller
{
    public function edit(): View
    {
        return view('auth.password');
    }

    public function update(UpdatePasswordRequest $request, ActivityLogger $logger): RedirectResponse
    {
        $user = $request->user();

        $user->forceFill([
            'password' => $request->validated('password'),
            'must_change_password' => false,
        ])->save();

        $request->session()->regenerate();
        $logger->log('auth.password_changed', $user);

        return redirect()->route('dashboard')->with('success', __('auth.password_changed'));
    }
}
