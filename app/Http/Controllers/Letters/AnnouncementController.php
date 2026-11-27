<?php

namespace App\Http\Controllers\Letters;

use App\Enums\AnnouncementStatus;
use App\Http\Controllers\Controller;
use App\Http\Requests\Letters\AnnouncementRequest;
use App\Models\Announcement;
use App\Services\ActivityLogger;
use App\Support\Paging;
use App\Support\PrivateFiles;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\View\View;
use Symfony\Component\HttpFoundation\StreamedResponse;
use Throwable;

/**
 * اعلان‌های مکتب (مرحله‌ی ۱۲). دیدن: همه‌ی کاربران (به اندازه‌ی مخاطب)؛ ساخت و انتشار: announcements.manage.
 * اعلان حذف نمی‌شود؛ بایگانی می‌شود.
 */
class AnnouncementController extends Controller
{
    public function __construct(private readonly ActivityLogger $logger) {}

    public function index(Request $request): View
    {
        $user = $request->user();
        $manager = $user->hasPermission('announcements.manage');
        $status = $manager ? AnnouncementStatus::tryFrom((string) $request->query('status')) : null;

        $announcements = Announcement::query()
            ->visibleTo($user)
            ->when(! $manager, fn ($q) => $q->active())
            ->when($manager && $status, fn ($q) => $q->where('status', $status))
            ->when($manager && ! $status, fn ($q) => $q->where('status', '!=', AnnouncementStatus::Archived))
            ->with('author')
            ->newestFirst()
            ->paginate(Paging::size())
            ->withQueryString();

        return view('announcements.index', ['announcements' => $announcements, 'manager' => $manager, 'status' => $status?->value]);
    }

    public function show(Request $request, Announcement $announcement): View
    {
        $this->authorizeView($request, $announcement);

        return view('announcements.show', ['announcement' => $announcement, 'print' => $request->boolean('print')]);
    }

    public function attachment(Request $request, Announcement $announcement): StreamedResponse
    {
        $this->authorizeView($request, $announcement);
        abort_unless(PrivateFiles::exists($announcement->attachment_path), 404);

        return PrivateFiles::download($announcement->attachment_path, (string) $announcement->attachment_name);
    }

    public function create(): View
    {
        return view('announcements.form', ['announcement' => new Announcement(['audience' => 'all'])]);
    }

    public function store(AnnouncementRequest $request): RedirectResponse
    {
        $announcement = $this->save(new Announcement, $request);

        return redirect()->route('announcements.show', $announcement)->with('success', __('announcements.saved'));
    }

    public function edit(Announcement $announcement): View
    {
        return view('announcements.form', ['announcement' => $announcement]);
    }

    public function update(AnnouncementRequest $request, Announcement $announcement): RedirectResponse
    {
        $this->save($announcement, $request);

        return redirect()->route('announcements.show', $announcement)->with('success', __('announcements.saved'));
    }

    /** انتشار، برگرداندن به پیش‌نویس یا بایگانی */
    public function status(Request $request, Announcement $announcement): RedirectResponse
    {
        $data = $request->validate(['status' => ['required', 'in:draft,published,archived']]);
        $new = AnnouncementStatus::from($data['status']);

        DB::transaction(function () use ($announcement, $new, $request) {
            $old = $announcement->status;
            $announcement->forceFill([
                'status' => $new,
                'published_at' => $new === AnnouncementStatus::Published ? ($announcement->published_at ?? now()) : $announcement->published_at,
                'updated_by' => $request->user()->id,
            ])->save();
            $this->logger->log('announcement.'.($new === AnnouncementStatus::Published ? 'published' : ($new === AnnouncementStatus::Archived ? 'archived' : 'updated')),
                $announcement, $announcement->title, ['status' => $old->label()], ['status' => $new->label()]);
        });

        return back()->with('success', __('announcements.status_changed', ['status' => $new->label()]));
    }

    private function save(Announcement $announcement, AnnouncementRequest $request): Announcement
    {
        $data = $request->validated();
        $file = $request->file('attachment');
        $newPath = $file ? PrivateFiles::store($file, 'announcements') : null;
        $oldPath = $announcement->attachment_path;
        $creating = ! $announcement->exists;

        try {
            DB::transaction(function () use ($announcement, $data, $file, $newPath, $request, $creating) {
                $announcement->fill([
                    'title' => $data['title'],
                    'body' => $data['body'],
                    'audience' => $data['audience'],
                    'is_pinned' => $data['is_pinned'],
                    'expires_on' => $data['expires_on'] ?? null,
                ]);
                if ($creating) {
                    $announcement->forceFill([
                        'status' => $data['publish'] ? AnnouncementStatus::Published : AnnouncementStatus::Draft,
                        'published_at' => $data['publish'] ? now() : null,
                        'created_by' => $request->user()->id,
                    ]);
                }
                if ($file) {
                    $announcement->forceFill([
                        'attachment_path' => $newPath, 'attachment_name' => mb_substr($file->getClientOriginalName(), 0, 255),
                        'attachment_mime' => $file->getMimeType(), 'attachment_size' => $file->getSize(),
                    ]);
                } elseif ($data['remove_attachment']) {
                    $announcement->forceFill(['attachment_path' => null, 'attachment_name' => null, 'attachment_mime' => null, 'attachment_size' => 0]);
                }
                $dirty = array_keys($announcement->getDirty());
                $announcement->forceFill(['updated_by' => $request->user()->id])->save();

                $this->logger->log($creating ? 'announcement.created' : 'announcement.updated', $announcement, $announcement->title,
                    new: $creating ? ['audience' => $announcement->audience->label(), 'status' => $announcement->status->label()] : ['fields' => implode('، ', $dirty)]);
            });
        } catch (Throwable $e) {
            PrivateFiles::delete($newPath);
            throw $e;
        }

        if ($oldPath && $oldPath !== $announcement->attachment_path) {
            PrivateFiles::delete($oldPath);
        }

        return $announcement;
    }

    private function authorizeView(Request $request, Announcement $announcement): void
    {
        $user = $request->user();
        if ($user->hasPermission('announcements.manage')) {
            return;
        }
        abort_unless($announcement->isActive() && $announcement->visibleTo($user), 404);
    }
}
