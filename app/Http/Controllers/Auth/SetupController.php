<?php

namespace App\Http\Controllers\Auth;

use App\Http\Controllers\Controller;
use App\Http\Requests\Auth\SetupRequest;
use App\Services\SetupService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Support\Facades\Auth;
use Illuminate\View\View;

/** راه‌اندازی نصب تازه (فقط وقتی school:install کد داده و هنوز مدیر سیستمی نیست؛ در غیر آن ۴۰۴) */
class SetupController extends Controller
{
    public function __construct(private readonly SetupService $setup) {}

    public function create(): View
    {
        abort_unless($this->setup->pending(), 404);

        return view('auth.setup');
    }

    public function store(SetupRequest $request): RedirectResponse
    {
        $user = $this->setup->complete($request->validated());

        Auth::login($user);
        $request->session()->regenerate();

        return redirect()->route('settings.school.edit')->with('success', __('setup.done'));
    }
}
