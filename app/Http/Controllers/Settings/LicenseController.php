<?php

namespace App\Http\Controllers\Settings;

use App\Http\Controllers\Controller;
use App\Services\LicenseService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

/** تنظیمات ← لایسنس (license.manage؛ فقط مدیر سیستم). فقط وضعیت و نصب فایل؛ هیچ قفلی ندارد. */
class LicenseController extends Controller
{
    public function __construct(private readonly LicenseService $license) {}

    public function index(): View
    {
        return view('settings.license.index', [
            'status' => $this->license->status(),
            'configured' => $this->license->configured(),
            'installationId' => $this->license->installationId(),
        ]);
    }

    public function store(Request $request): RedirectResponse
    {
        $request->validate(['license' => ['required', 'file', 'max:64', 'mimetypes:application/json,text/plain']], [], ['license' => __('license.file')]);

        $this->license->install((string) $request->file('license')->get(), $request->user());

        return back()->with('success', __('license.installed'));
    }
}
