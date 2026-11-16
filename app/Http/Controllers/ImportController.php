<?php

namespace App\Http\Controllers;

use App\Exceptions\ImportFileException;
use App\Services\ImportService;
use App\Support\Digits;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;
use Illuminate\View\View;
use Symfony\Component\HttpFoundation\Response;
use Throwable;

/**
 * ورود گروهی از Excel: انتخاب نوع و آپلود ← بررسی (پیش‌نمایش) ← ذخیره‌ی سطرهای درست.
 * دانش‌آموزان: students.manage؛ کارکنان: staff.manage. فایل آپلودی تا ذخیره در storage/app/private/imports می‌ماند.
 */
class ImportController extends Controller
{
    private const DIR = 'imports';

    public function __construct(private readonly ImportService $import) {}

    public function index(Request $request): View
    {
        $types = $this->allowedTypes($request);
        abort_if($types === [], 403);
        $this->cleanupOldFiles();

        return view('import.index', [
            'types' => $types,
            'type' => in_array($request->query('type'), $types, true) ? $request->query('type') : $types[0],
        ]);
    }

    public function template(Request $request, string $type): Response
    {
        $this->authorizeType($request, $type);

        return response($this->import->template($type), 200, [
            'Content-Type' => 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet',
            'Content-Disposition' => 'attachment; filename="import-'.$type.'.xlsx"',
        ]);
    }

    public function upload(Request $request): RedirectResponse
    {
        $data = $request->validate([
            'type' => ['required', Rule::in(ImportService::TYPES)],
            'file' => ['required', 'file', 'max:2048', 'extensions:xlsx,csv'],
        ], [], ['file' => __('import.file')]);
        $this->authorizeType($request, $data['type']);

        $extension = strtolower($request->file('file')->getClientOriginalExtension());
        $token = Str::random(32).'.'.$extension;
        Storage::disk('local')->putFileAs(self::DIR, $request->file('file'), $token);

        return redirect()->route('import.preview', ['type' => $data['type'], 'token' => $token]);
    }

    public function preview(Request $request): View|RedirectResponse
    {
        [$type, $token, $path] = $this->resolve($request);

        try {
            $result = $this->import->preview($type, $path, pathinfo($token, PATHINFO_EXTENSION));
        } catch (ImportFileException $e) {
            Storage::disk('local')->delete(self::DIR.'/'.$token);

            return redirect()->route('import.index', ['type' => $type])->with('error', $e->getMessage());
        } catch (Throwable $e) {
            Storage::disk('local')->delete(self::DIR.'/'.$token);
            report($e);

            return redirect()->route('import.index', ['type' => $type])->with('error', __('import.bad_file'));
        }

        $counts = collect($result['rows'])->countBy('status');

        return view('import.preview', [
            'type' => $type,
            'token' => $token,
            'columns' => $this->import->columns($type),
            'rows' => $result['rows'],
            'unknown' => $result['unknown'],
            'missing' => $result['missing'],
            'counts' => ['ok' => $counts['ok'] ?? 0, 'error' => $counts['error'] ?? 0, 'duplicate' => $counts['duplicate'] ?? 0],
        ]);
    }

    public function store(Request $request): RedirectResponse
    {
        [$type, $token, $path] = $this->resolve($request);

        $result = $this->import->preview($type, $path, pathinfo($token, PATHINFO_EXTENSION));
        $summary = $this->import->import($type, $result['rows'], $request->user());
        Storage::disk('local')->delete(self::DIR.'/'.$token);

        $target = $type === 'students' ? route('students.index') : route('staff.index');

        return redirect()->to($target)->with('success', __('import.done', [
            'imported' => Digits::toPersian((string) $summary['imported']),
            'enrolled' => $summary['enrolled'] > 0 ? __('import.done_enrolled', ['count' => Digits::toPersian((string) $summary['enrolled'])]) : '',
            'skipped' => Digits::toPersian((string) $summary['skipped']),
        ]));
    }

    /** @return array{0: string, 1: string, 2: string} */
    private function resolve(Request $request): array
    {
        $type = (string) $request->input('type');
        $token = (string) $request->input('token');
        abort_unless(in_array($type, ImportService::TYPES, true), 404);
        $this->authorizeType($request, $type);

        $disk = Storage::disk('local');
        if (! preg_match('/^[A-Za-z0-9]{32}\.(xlsx|csv)$/', $token) || ! $disk->exists(self::DIR.'/'.$token)) {
            abort(redirect()->route('import.index', ['type' => $type])->with('error', __('import.expired')));
        }

        return [$type, $token, $disk->path(self::DIR.'/'.$token)];
    }

    /** @return list<string> */
    private function allowedTypes(Request $request): array
    {
        return array_values(array_filter(ImportService::TYPES, fn ($t) => $request->user()->hasPermission($t === 'students' ? 'students.manage' : 'staff.manage')));
    }

    private function authorizeType(Request $request, string $type): void
    {
        abort_unless(in_array($type, $this->allowedTypes($request), true), 403);
    }

    /** فایل‌هایی که بیشتر از یک روز مانده‌اند (بررسی شده ولی ذخیره نشده) */
    private function cleanupOldFiles(): void
    {
        $disk = Storage::disk('local');
        foreach ($disk->files(self::DIR) as $file) {
            if ($disk->lastModified($file) < now()->subDay()->getTimestamp()) {
                $disk->delete($file);
            }
        }
    }
}
