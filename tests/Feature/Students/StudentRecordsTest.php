<?php

namespace Tests\Feature\Students;

use App\Enums\EnrollmentStatus;
use App\Enums\StudentStatus;
use App\Models\ActivityLog;
use App\Models\FeeType;
use App\Models\LetterTemplate;
use App\Models\Mark;
use App\Models\Setting;
use App\Models\StudentDocument;
use App\Models\StudentTransfer;
use App\Services\FinanceService;
use Database\Seeders\LetterTemplateSeeder;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Storage;
use Tests\Feature\Exams\ExamTestCase;

/** مرحله‌ی ۱۳: دسته‌ی اسناد، انتقال میان مکاتب، سند انتقال و فایل سوابق، بایگانی، کارت هویت */
class StudentRecordsTest extends ExamTestCase
{
    protected function setUp(): void
    {
        parent::setUp();
        Storage::fake('local');
        Carbon::setTestNow('2026-11-20 10:00:00');
    }

    protected function tearDown(): void
    {
        Carbon::setTestNow();
        parent::tearDown();
    }

    private function outPayload(array $overrides = []): array
    {
        return array_merge([
            'other_school' => 'لیسه‌ی مقصد آزمایشی',
            'other_province' => 'هرات',
            'transfer_date' => '1405/08/29',
            'requested_on' => '1405/08/20',
            'approved_on' => '1405/08/25',
            'reason' => 'تبدیلی وظیفه‌ی پدر',
            'reference_number' => '۱۲۳',
        ], $overrides);
    }

    public function test_documents_have_category_and_preview(): void
    {
        $student = $this->students[0]->student;
        $this->actingAs($this->principal)->post(route('students.documents.store', $student), [
            'title' => 'تذکره', 'category' => 'identity', 'file' => UploadedFile::fake()->image('t.jpg'),
        ])->assertRedirect();

        $doc = StudentDocument::latest('id')->firstOrFail();
        $this->assertSame('identity', $doc->category->value);
        $this->actingAs($this->principal)->get(route('students.show', $student))->assertSee(__('enums.document_category.identity'));

        $preview = $this->actingAs($this->principal)->get(route('students.documents.show', [$student, $doc, 'preview' => 1]));
        $preview->assertOk();
        $this->assertStringNotContainsString('attachment', (string) $preview->headers->get('Content-Disposition'));
        $this->assertStringContainsString('attachment', (string) $this->actingAs($this->principal)->get(route('students.documents.show', [$student, $doc]))->headers->get('Content-Disposition'));

        $this->actingAs($this->principal)->post(route('students.documents.store', $student), ['title' => 'x', 'category' => 'bad', 'file' => UploadedFile::fake()->image('t.jpg')])
            ->assertSessionHasErrors('category');
    }

    public function test_transfer_out_closes_enrollment_keeps_history_and_prints_certificate(): void
    {
        $enrollment = $this->students[0];
        $student = $enrollment->student;
        Mark::create(['enrollment_id' => $enrollment->id, 'exam_id' => $this->mid->id, 'subject_id' => $this->math->id, 'mark' => 30, 'entered_by' => $this->principal->id]);

        $this->actingAs($this->principal)->get(route('student-transfers.out.create', $student))->assertOk()->assertSee($this->section->label);
        $this->actingAs($this->principal)->post(route('student-transfers.out.store', $student), $this->outPayload())->assertRedirect();

        $transfer = StudentTransfer::latest('id')->firstOrFail();
        $this->assertSame('out', $transfer->direction->value);
        $this->assertSame($enrollment->id, $transfer->enrollment_id);
        $this->assertSame('123', $transfer->reference_number);
        $this->assertSame(EnrollmentStatus::Transferred, $enrollment->fresh()->status);
        $this->assertSame(StudentStatus::Transferred, $student->fresh()->status);
        $this->assertSame(1, Mark::where('enrollment_id', $enrollment->id)->count());
        $this->assertTrue(ActivityLog::where('action', 'student.transfer_out')->exists());

        // سند انتقال
        $this->actingAs($this->principal)->get(route('student-transfers.record', [$student, 'transfer' => $transfer->id]))
            ->assertOk()->assertSee(__('transfers.certificate_title'))->assertSee('لیسه‌ی مقصد آزمایشی')->assertSee('تبدیلی وظیفه‌ی پدر')->assertSee($this->section->gradeLevel->name);

        // مکتوب انتقال از قالب، با مکتب مقصد پر
        $this->seed(LetterTemplateSeeder::class);
        $template = LetterTemplate::where('type', 'transfer')->firstOrFail();
        $this->actingAs($this->principal)->get(route('letters.outgoing.create', ['template' => $template->id, 'student' => $student->admission_no, 'transfer' => $transfer->id]))
            ->assertOk()->assertSee('لیسه‌ی مقصد آزمایشی')->assertDontSee('{other_school}');

        // دانش‌آموز منتقل‌شده دوباره منتقل نمی‌شود
        $this->actingAs($this->principal)->post(route('student-transfers.out.store', $student), $this->outPayload())->assertSessionHasErrors('other_school');
        $this->assertSame(1, StudentTransfer::count());
    }

    public function test_outstanding_fees_need_confirmation(): void
    {
        Setting::put('fees_enabled', '1');
        $enrollment = $this->students[1];
        $type = FeeType::create(['name' => 'فیس تست انتقال', 'period' => 'once', 'sort_order' => 9]);
        app(FinanceService::class)->issueSingle($enrollment, $type, 'یک‌بار', 150000, 0, null, null, $this->principal);

        $this->actingAs($this->principal)->get(route('student-transfers.out.create', $enrollment->student))->assertSee(__('transfers.clearance_confirmed'));
        $this->actingAs($this->principal)->post(route('student-transfers.out.store', $enrollment->student), $this->outPayload())->assertSessionHasErrors('clearance_confirmed');
        $this->assertSame(0, StudentTransfer::count());

        $this->actingAs($this->principal)->post(route('student-transfers.out.store', $enrollment->student), $this->outPayload(['clearance_confirmed' => '1', 'clearance_note' => 'پدرش بعداً می‌پردازد']))->assertRedirect();
        $this->assertSame('1500.00', StudentTransfer::latest('id')->first()->outstanding_balance);
    }

    public function test_cancel_transfer_re_enrolls_in_same_section(): void
    {
        $enrollment = $this->students[0];
        $this->actingAs($this->principal)->post(route('student-transfers.out.store', $enrollment->student), $this->outPayload());
        $transfer = StudentTransfer::latest('id')->firstOrFail();

        $this->actingAs($this->principal)->post(route('student-transfers.cancel', $transfer), ['reason' => 'اشتباه'])->assertRedirect();
        $this->assertSame('cancelled', $transfer->fresh()->status);
        $this->assertSame(EnrollmentStatus::Active, $enrollment->fresh()->status);
        $this->assertSame($this->section->id, $enrollment->fresh()->section_id);
        $this->assertSame(StudentStatus::Active, $enrollment->student->fresh()->status);
        $this->actingAs($this->principal)->post(route('student-transfers.cancel', $transfer), ['reason' => 'دوباره'])->assertSessionHasErrors('reason');
    }

    public function test_record_file_exports_and_imports_into_another_profile(): void
    {
        $enrollment = $this->students[0];
        $student = $enrollment->student;
        $this->actingAs($this->principal)->post(route('student-transfers.out.store', $student), $this->outPayload());

        $response = $this->actingAs($this->principal)->get(route('student-transfers.package', $student));
        $response->assertOk();
        $json = json_decode($response->getContent(), true);
        $this->assertSame('maktab-student-record', $json['format']);
        $this->assertSame($student->father_name, $json['student']['father_name']);
        $this->assertSame('لیسه‌ی مقصد آزمایشی', $json['transfer']['to_school']);
        $this->assertNotEmpty($json['history']);
        $this->assertTrue(ActivityLog::where('action', 'student.record_exported')->exists());

        // مکتب مقصد (اینجا یک دانش‌آموز دیگر به‌جای نصب دیگر) فایل را آپلود می‌کند
        $arriving = $this->students[2]->student;
        $file = UploadedFile::fake()->createWithContent('record.json', $response->getContent());
        $this->actingAs($this->principal)->post(route('student-transfers.in.store', $arriving), [
            'other_school' => 'لیسه‌ی مبدأ', 'transfer_date' => '1405/08/29', 'reason' => 'تبدیلی', 'last_grade' => 'هفتم', 'package' => $file,
        ])->assertRedirect();

        $in = StudentTransfer::latest('id')->firstOrFail();
        $this->assertSame('in', $in->direction->value);
        $this->assertSame($student->father_name, $in->previous_records['student']['father_name']);
        $this->actingAs($this->principal)->get(route('students.show', $arriving))
            ->assertSee(__('transfers.previous_records'))
            ->assertSee(__('transfers.name_mismatch', ['name' => $student->full_name]));
        $this->assertSame(StudentStatus::Active, $arriving->fresh()->status);

        $bad = UploadedFile::fake()->createWithContent('bad.json', '{"format":"other"}');
        $this->actingAs($this->principal)->post(route('student-transfers.in.store', $arriving), [
            'other_school' => 'x', 'transfer_date' => '1405/08/29', 'reason' => 'y', 'package' => $bad,
        ])->assertSessionHasErrors('package');
    }

    public function test_archive_lists_former_students_and_saves_certificate(): void
    {
        $graduate = $this->students[1]->student;
        $graduate->forceFill(['status' => StudentStatus::Graduated, 'status_changed_on' => '2026-11-15'])->save();
        $this->actingAs($this->principal)->post(route('student-transfers.out.store', $this->students[0]->student), $this->outPayload());

        $this->actingAs($this->principal)->get(route('student-archive.index'))
            ->assertOk()->assertSee($graduate->full_name)->assertSee($this->students[0]->student->full_name)->assertDontSee($this->students[2]->student->full_name);
        $this->actingAs($this->principal)->get(route('student-archive.index', ['status' => 'graduated']))
            ->assertSee($graduate->full_name)->assertDontSee($this->students[0]->student->full_name);
        $this->actingAs($this->principal)->get(route('student-archive.index', ['print' => 1]))->assertOk();
        $this->assertStringContainsString($graduate->father_name, $this->actingAs($this->principal)->get(route('student-archive.export'))->streamedContent());

        $this->actingAs($this->principal)->patch(route('student-archive.certificate', $graduate), ['certificate_no' => 'F-77', 'certificate_date' => '1405/08/26'])->assertRedirect();
        $this->assertSame('F-77', $graduate->fresh()->certificate_no);
        $this->assertSame('2026-11-17', $graduate->fresh()->certificate_date->toDateString());
        $this->actingAs($this->principal)->patch(route('student-archive.certificate', $this->students[2]->student), ['certificate_no' => 'X'])->assertSessionHasErrors('certificate_no');
    }

    public function test_id_cards_for_section_and_single_student(): void
    {
        $this->actingAs($this->principal)->get(route('student-cards.index'))->assertOk()->assertSee($this->section->label);
        $page = $this->actingAs($this->principal)->get(route('student-cards.index', ['section' => $this->section->id, 'print' => 1]));
        $page->assertOk();
        foreach ($this->students as $e) {
            $page->assertSee($e->student->full_name);
        }
        $this->actingAs($this->principal)->get(route('student-cards.index', ['student' => $this->students[0]->student_id, 'print' => 1]))
            ->assertSee($this->students[0]->student->full_name)->assertDontSee($this->students[1]->student->full_name);
    }

    public function test_teacher_has_no_access(): void
    {
        $student = $this->students[0]->student;
        $this->actingAs($this->teacher)->get(route('student-transfers.index'))->assertForbidden();
        $this->actingAs($this->teacher)->get(route('student-archive.index'))->assertForbidden();
        $this->actingAs($this->teacher)->get(route('student-cards.index'))->assertForbidden();
        $this->actingAs($this->teacher)->post(route('student-transfers.out.store', $student), $this->outPayload())->assertForbidden();
        $this->actingAs($this->teacher)->get(route('student-transfers.package', $student))->assertForbidden();
    }
}
