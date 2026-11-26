<?php

namespace Tests\Feature\Letters;

use App\Enums\AnnouncementStatus;
use App\Enums\Role;
use App\Models\ActivityLog;
use App\Models\Announcement;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

/** مرحله‌ی ۱۲: اعلان‌ها */
class AnnouncementsTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();
        Storage::fake('local');
    }

    private function make(array $overrides = []): Announcement
    {
        return Announcement::create(array_merge([
            'title' => 'اعلان آزمایشی', 'body' => 'متن', 'audience' => 'all',
            'status' => AnnouncementStatus::Published, 'published_at' => now(),
        ], $overrides));
    }

    public function test_principal_publishes_announcement_with_attachment(): void
    {
        $principal = $this->userWithRole(Role::Principal);
        $this->actingAs($principal)->post(route('announcements.store'), [
            'title' => 'جلسه‌ی والدین', 'body' => "روز پنجشنبه\nساعت ۹", 'audience' => 'parents', 'publish' => '1', 'is_pinned' => '1',
            'attachment' => UploadedFile::fake()->create('plan.pdf', 50, 'application/pdf'),
        ])->assertRedirect();

        $a = Announcement::latest('id')->firstOrFail();
        $this->assertSame(AnnouncementStatus::Published, $a->status);
        $this->assertNotNull($a->published_at);
        $this->assertTrue($a->is_pinned);
        Storage::disk('local')->assertExists($a->attachment_path);
        $this->assertTrue(ActivityLog::where('action', 'announcement.created')->exists());

        $teacher = $this->userWithRole(Role::Teacher);
        $this->actingAs($teacher)->get('/')->assertSee('جلسه‌ی والدین');
        $this->actingAs($teacher)->get(route('announcements.show', $a))->assertOk();
        $this->actingAs($teacher)->get(route('announcements.attachment', $a))->assertOk();
        $this->actingAs($teacher)->get(route('announcements.show', [$a, 'print' => 1]))->assertOk()->assertSee('جلسه‌ی والدین');
    }

    public function test_drafts_expired_and_teacher_only_announcements_are_hidden_correctly(): void
    {
        $draft = $this->make(['title' => 'پیش‌نویس پنهان', 'status' => AnnouncementStatus::Draft, 'published_at' => null]);
        $this->make(['title' => 'کهنه‌شده', 'expires_on' => now()->subDay()->toDateString()]);
        $teachersOnly = $this->make(['title' => 'فقط معلمان', 'audience' => 'teachers']);
        $this->make(['title' => 'برای همه']);

        $accountant = $this->userWithRole(Role::Accountant);
        $this->actingAs($accountant)->get(route('announcements.index'))
            ->assertSee('برای همه')->assertDontSee('پیش‌نویس پنهان')->assertDontSee('کهنه‌شده')->assertDontSee('فقط معلمان');
        $this->actingAs($accountant)->get(route('announcements.show', $draft))->assertNotFound();
        $this->actingAs($accountant)->get(route('announcements.show', $teachersOnly))->assertNotFound();

        $teacher = $this->userWithRole(Role::Teacher);
        $this->actingAs($teacher)->get(route('announcements.index'))->assertSee('فقط معلمان')->assertDontSee('پیش‌نویس پنهان');

        $office = $this->userWithRole(Role::Office);
        $this->actingAs($office)->get(route('announcements.index'))->assertSee('پیش‌نویس پنهان');
    }

    public function test_status_changes_are_logged_and_archive_hides(): void
    {
        $office = $this->userWithRole(Role::Office);
        $a = $this->make(['status' => AnnouncementStatus::Draft, 'published_at' => null]);

        $this->actingAs($office)->patch(route('announcements.status', $a), ['status' => 'published'])->assertRedirect();
        $this->assertNotNull($a->fresh()->published_at);
        $this->actingAs($office)->patch(route('announcements.status', $a), ['status' => 'archived']);
        $this->assertSame(AnnouncementStatus::Archived, $a->fresh()->status);
        $this->assertTrue(ActivityLog::where('action', 'announcement.archived')->exists());

        $teacher = $this->userWithRole(Role::Teacher);
        $this->actingAs($teacher)->get(route('announcements.show', $a))->assertNotFound();
    }

    public function test_only_managers_write(): void
    {
        $teacher = $this->userWithRole(Role::Teacher);
        $a = $this->make();
        $this->actingAs($teacher)->get(route('announcements.create'))->assertForbidden();
        $this->actingAs($teacher)->post(route('announcements.store'), ['title' => 'x', 'body' => 'y', 'audience' => 'all'])->assertForbidden();
        $this->actingAs($teacher)->patch(route('announcements.status', $a), ['status' => 'archived'])->assertForbidden();
        $this->actingAs($teacher)->put(route('announcements.update', $a), ['title' => 'x', 'body' => 'y', 'audience' => 'all'])->assertForbidden();
    }

    public function test_body_is_escaped(): void
    {
        $this->make(['title' => 'امنیت', 'body' => '<script>alert(1)</script>']);
        $user = $this->userWithRole(Role::Accountant);
        $this->actingAs($user)->get(route('announcements.show', Announcement::latest('id')->first()))
            ->assertDontSee('<script>alert(1)</script>', false)
            ->assertSee('&lt;script&gt;', false);
    }
}
