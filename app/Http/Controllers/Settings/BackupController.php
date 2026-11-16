<?php

namespace App\Http\Controllers\Settings;

use App\Http\Controllers\Controller;
use App\Services\ActivityLogger;
use App\Services\BackupService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;
use Symfony\Component\HttpFoundation\BinaryFileResponse;
use Throwable;

/** تنظیمات ← پشتیبان و بازیابی (backups.manage). بازیابی فقط روی سرور با tools\restore.bat. */
class BackupController extends Controller
{
    public function __construct(private readonly BackupService $backups) {}

    public function index(): View
    {
        return view('settings.backups.index', [
            'backups' => $this->backups->all(),
            'due' => $this->backups->isDue(),
            'directory' => $this->backups->directory(),
        ]);
    }

    public function store(Request $request): RedirectResponse
    {
        try {
            $backup = $this->backups->create('manual', $request->user());
        } catch (Throwable $e) {
            report($e);

            return back()->with('error', __('backup.failed', ['message' => $e->getMessage()]));
        }

        return back()->with('success', __('backup.created', ['name' => $backup['name']]));
    }

    public function download(Request $request, string $name, ActivityLogger $logger): BinaryFileResponse
    {
        $path = $this->backups->find($name);
        abort_if($path === null, 404);
        $logger->log('backup.downloaded', null, $name);

        return response()->download($path, $name, ['Content-Type' => 'application/zip']);
    }
}
