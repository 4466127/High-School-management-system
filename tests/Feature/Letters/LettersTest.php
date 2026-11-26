<?php

namespace Tests\Feature\Letters;

use App\Enums\LetterStatus;
use App\Enums\Role;
use App\Models\ActivityLog;
use App\Models\Letter;
use App\Models\LetterTemplate;
use App\Models\Setting;
use App\Models\Staff;
use App\Models\Student;
use App\Services\EnrollmentService;
use Database\Seeders\LetterTemplateSeeder;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

/** مرحله‌ی ۱۲: مکاتیب صادره و وارده، قالب و جای‌نشان، شماره‌ی پشت‌سرهم، باطل، ضمیمه، چاپ */
class LettersTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();
        Storage::fake('local');
        Carbon::setTestNow('2026-11-20 10:00:00');
        $this->seed(LetterTemplateSeeder::class);
    }

    protected function tearDown(): void
    {
        Carbon::setTestNow();
        parent::tearDown();
    }

    private function draft(array $overrides = []): array
    {
        return array_merge([
            'type' => 'general',
            'letter_date' => '1405/08/29',
            'recipient' => 'آمر محترم',
            'organization' => 'ریاست آزمایشی',
            'subject' => 'موضوع آزمایشی',
            'body' => "محترما!\nمتن آزمایشی.",
        ], $overrides);
    }

    public function test_template_fills_student_placeholders(): void
    {
        $office = $this->userWithRole(Role::Office);
        Setting::put('school_name', 'لیسه‌ی آزمایشی');
        $year = $this->makeYear();
        $section = $this->makeSection($year);
        $student = Student::factory()->create(['first_name' => 'احمد', 'last_name' => 'کریمی', 'father_name' => 'محمود', 'admission_no' => '7788']);
        app(EnrollmentService::class)->enroll($student, $section, '2026-03-21');
        $template = LetterTemplate::where('type', 'enrollment_confirmation')->firstOrFail();

        $this->actingAs($office)->get(route('letters.outgoing.create', ['template' => $template->id, 'student' => '۷۷۸۸']))
            ->assertOk()
            ->assertSee('احمد کریمی')
            ->assertSee('محمود')
            ->assertSee('لیسه‌ی آزمایشی')
            ->assertSee('۷۷۸۸')
            ->assertDontSee('{student_name}');

        $this->actingAs($office)->get(route('letters.outgoing.create', ['student' => '0000']))->assertSee(__('letters.student_not_found'));
    }

    public function test_draft_issue_numbers_sequentially_and_locks(): void
    {
        $office = $this->userWithRole(Role::Office);
        Setting::put('letter_outgoing_prefix', 'ص-');

        $this->actingAs($office)->post(route('letters.outgoing.store'), $this->draft())->assertRedirect();
        $first = Letter::latest('id')->firstOrFail();
        $this->assertSame(LetterStatus::Draft, $first->status);
        $this->assertNull($first->number);

        // ویرایش پیش‌نویس
        $this->actingAs($office)->put(route('letters.outgoing.update', $first), $this->draft(['subject' => 'موضوع اصلاح‌شده']))->assertRedirect();
        $this->assertSame('موضوع اصلاح‌شده', $first->fresh()->subject);

        $this->actingAs($office)->post(route('letters.outgoing.issue', $first))->assertRedirect();
        $first->refresh();
        $this->assertSame(LetterStatus::Issued, $first->status);
        $this->assertSame('ص-1405/001', $first->number);

        $this->actingAs($office)->post(route('letters.outgoing.store'), $this->draft(['subject' => 'دوم']));
        $second = Letter::latest('id')->firstOrFail();
        $this->actingAs($office)->post(route('letters.outgoing.issue', $second));
        $this->assertSame('ص-1405/002', $second->fresh()->number);

        // بعد از صدور: ویرایش و صدور دوباره ممنوع
        $this->actingAs($office)->put(route('letters.outgoing.update', $first), $this->draft(['subject' => 'تغییر']))->assertSessionHasErrors('letter');
        $this->assertSame('موضوع اصلاح‌شده', $first->fresh()->subject);
        $this->actingAs($office)->get(route('letters.outgoing.edit', $first))->assertNotFound();
        $this->actingAs($office)->post(route('letters.outgoing.issue', $first))->assertSessionHasErrors('letter');

        // باطل: شماره دوباره داده نمی‌شود، مکتوب حذف نمی‌شود
        $this->actingAs($office)->post(route('letters.cancel', $second), ['reason' => 'اشتباه تایپی'])->assertRedirect();
        $this->assertSame(LetterStatus::Cancelled, $second->fresh()->status);
        $this->actingAs($office)->post(route('letters.outgoing.store'), $this->draft(['subject' => 'سوم']));
        $third = Letter::latest('id')->firstOrFail();
        $this->actingAs($office)->post(route('letters.outgoing.issue', $third));
        $this->assertSame('ص-1405/003', $third->fresh()->number);
        $this->assertSame(3, Letter::count());

        foreach (['letter.created', 'letter.updated', 'letter.issued', 'letter.cancelled'] as $action) {
            $this->assertTrue(ActivityLog::where('action', $action)->exists(), $action);
        }
    }

    public function test_unfilled_placeholder_blocks_issue(): void
    {
        $office = $this->userWithRole(Role::Office);
        $this->actingAs($office)->post(route('letters.outgoing.store'), $this->draft(['body' => 'متن با {student_name} پر نشده']));
        $letter = Letter::latest('id')->firstOrFail();

        $this->actingAs($office)->post(route('letters.outgoing.issue', $letter))->assertSessionHasErrors('letter');
        $this->assertSame(LetterStatus::Draft, $letter->fresh()->status);
    }

    public function test_print_shows_letterhead_number_signatures_and_stamp(): void
    {
        $principal = $this->userWithRole(Role::Principal);
        Setting::put('header_line', 'سطر سربرگ مکتوب');
        Setting::put('principal_name', 'نام مدیر آزمایشی');
        Setting::put('letter_signature_2', 'سرمعلم');
        $this->actingAs($principal)->post(route('letters.outgoing.store'), $this->draft());
        $letter = Letter::latest('id')->firstOrFail();

        $this->actingAs($principal)->get(route('letters.outgoing.show', [$letter, 'print' => 1]))
            ->assertOk()->assertSee(__('letters.draft_watermark'));

        $this->actingAs($principal)->post(route('letters.outgoing.issue', $letter));
        $this->actingAs($principal)->get(route('letters.outgoing.show', [$letter, 'print' => 1]))
            ->assertOk()
            ->assertDontSee(__('letters.draft_watermark'))
            ->assertSee('سطر سربرگ مکتوب')
            ->assertSee('۱۴۰۵/۰۰۱')
            ->assertSee('نام مدیر آزمایشی')
            ->assertSee('سرمعلم')
            ->assertSee(__('letters.stamp'))
            ->assertSee('متن آزمایشی.');
    }

    public function test_incoming_register_with_scan_reply_and_pending(): void
    {
        $office = $this->userWithRole(Role::Office);
        $this->actingAs($office)->post(route('letters.incoming.store'), [
            'type' => 'general',
            'letter_date' => '1405/08/25',
            'received_on' => '1405/08/28',
            'organization' => 'ریاست معارف آزمایشی',
            'external_number' => '۴۵۶',
            'subject' => 'درخواست آمار',
            'needs_response' => '1',
            'scan' => UploadedFile::fake()->create('scan.pdf', 100, 'application/pdf'),
        ])->assertRedirect();

        $incoming = Letter::latest('id')->firstOrFail();
        $this->assertSame(LetterStatus::Issued, $incoming->status);
        $this->assertSame('1405/001', $incoming->number);
        $this->assertSame('456', $incoming->external_number);
        $this->assertCount(1, $incoming->attachments);
        Storage::disk('local')->assertExists($incoming->attachments->first()->file_path);
        $this->assertTrue($incoming->awaitingResponse());
        $this->actingAs($office)->get(route('letters.incoming.index'))->assertSee(__('letters.awaiting_response'));

        // جواب: پیش‌نویس هنوز جواب حساب نمی‌شود، صادر شده حساب می‌شود
        $this->actingAs($office)->get(route('letters.outgoing.create', ['reply_to' => $incoming->id]))->assertOk()->assertSee('ریاست معارف آزمایشی');
        $this->actingAs($office)->post(route('letters.outgoing.store'), $this->draft(['reply_to_id' => $incoming->id]));
        $reply = Letter::latest('id')->firstOrFail();
        $this->assertTrue($incoming->fresh()->awaitingResponse());
        $this->actingAs($office)->post(route('letters.outgoing.issue', $reply));
        $this->assertFalse($incoming->fresh()->awaitingResponse());

        // ضمیمه‌ی مکتوب ثبت‌شده حذف نمی‌شود؛ دانلود کار می‌کند
        $att = $incoming->attachments->first();
        $this->actingAs($office)->get(route('letters.attachments.show', [$incoming, $att]))->assertOk();
        $this->actingAs($office)->delete(route('letters.attachments.destroy', [$incoming, $att]))->assertSessionHasErrors('file');
        // ضمیمه‌ی مکتوب دیگر با این آدرس باز نمی‌شود
        $this->actingAs($office)->get(route('letters.attachments.show', [$reply, $att]))->assertNotFound();
    }

    public function test_search_filter_and_csv(): void
    {
        $office = $this->userWithRole(Role::Office);
        $this->actingAs($office)->post(route('letters.outgoing.store'), $this->draft(['subject' => 'مکتوب ریاست', 'organization' => 'وزارت آزمایشی']));
        $this->actingAs($office)->post(route('letters.outgoing.store'), $this->draft(['subject' => 'دعوت جلسه', 'type' => 'invitation']));

        $this->actingAs($office)->get(route('letters.outgoing.index', ['q' => 'وزارت']))->assertSee('مکتوب ریاست')->assertDontSee('دعوت جلسه');
        $this->actingAs($office)->get(route('letters.outgoing.index', ['type' => 'invitation']))->assertSee('دعوت جلسه')->assertDontSee('مکتوب ریاست');
        $csv = $this->actingAs($office)->get(route('letters.export', ['direction' => 'outgoing']));
        $csv->assertOk();
        $this->assertStringContainsString('دعوت جلسه', $csv->streamedContent());
    }

    public function test_templates_are_managed_and_staff_placeholders_work(): void
    {
        $principal = $this->userWithRole(Role::Principal);
        $this->actingAs($principal)->get(route('letters.templates.index'))->assertOk()->assertSee('{student_name}');
        $this->actingAs($principal)->post(route('letters.templates.store'), [
            'name' => 'قالب کارمند', 'type' => 'employment_confirmation', 'subject' => 'تصدیق {staff_name}', 'body' => '{staff_name} وظیفه‌ی {staff_position}', 'is_active' => '1',
        ])->assertRedirect(route('letters.templates.index'));
        $template = LetterTemplate::where('name', 'قالب کارمند')->firstOrFail();
        $staff = Staff::factory()->create(['first_name' => 'زرغونه', 'last_name' => 'آزمایشی', 'position' => 'سرمعلم']);

        $this->actingAs($principal)->get(route('letters.outgoing.create', ['template' => $template->id, 'staff' => $staff->id]))
            ->assertSee('زرغونه آزمایشی وظیفه‌ی سرمعلم');

        $this->actingAs($principal)->put(route('letters.templates.update', $template), ['name' => 'قالب کارمند', 'type' => 'employment_confirmation', 'body' => 'متن'])->assertRedirect();
        $this->assertFalse($template->fresh()->is_active);
    }

    public function test_only_letter_managers_have_access(): void
    {
        foreach ([Role::Teacher, Role::Accountant, Role::CourseManager] as $role) {
            $user = $this->userWithRole($role);
            $this->actingAs($user)->get(route('letters.outgoing.index'))->assertForbidden();
            $this->actingAs($user)->get(route('letters.templates.index'))->assertForbidden();
            $this->actingAs($user)->post(route('letters.outgoing.store'), $this->draft())->assertForbidden();
        }
        $this->assertSame(0, Letter::count());
    }

    public function test_attachment_upload_rejects_wrong_type(): void
    {
        $office = $this->userWithRole(Role::Office);
        $this->actingAs($office)->post(route('letters.outgoing.store'), $this->draft());
        $letter = Letter::latest('id')->firstOrFail();

        $this->actingAs($office)->post(route('letters.attachments.store', $letter), ['title' => 'بد', 'file' => UploadedFile::fake()->create('x.exe', 10, 'application/octet-stream')])
            ->assertSessionHasErrors('file');
        $this->actingAs($office)->post(route('letters.attachments.store', $letter), ['title' => 'اسکن', 'file' => UploadedFile::fake()->image('s.jpg')])
            ->assertSessionHasNoErrors();
        $att = $letter->attachments()->firstOrFail();
        // پیش‌نویس: ضمیمه حذف می‌شود
        $this->actingAs($office)->delete(route('letters.attachments.destroy', [$letter, $att]))->assertSessionHasNoErrors();
        Storage::disk('local')->assertMissing($att->file_path);
    }
}
