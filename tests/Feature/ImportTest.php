<?php

namespace Tests\Feature;

use App\Enums\EducationLevel;
use App\Enums\Gender;
use App\Enums\Role;
use App\Enums\StaffType;
use App\Models\ActivityLog;
use App\Models\Staff;
use App\Models\Student;
use App\Support\Xlsx;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

/** ورود گروهی دانش‌آموزان و کارکنان از Excel/CSV. */
class ImportTest extends TestCase
{
    private const STUDENT_HEADER = ['نمبر اساس', 'نام *', 'تخلص', 'نام پدر *', 'جنسیت *', 'تاریخ تولد', 'شماره تماس', 'صنف', 'بخش', 'نام سرپرست', 'نسبت سرپرست'];

    protected function setUp(): void
    {
        parent::setUp();
        Storage::fake('local');
    }

    private function xlsx(array $rows, string $name = 'list.xlsx'): UploadedFile
    {
        return UploadedFile::fake()->createWithContent($name, Xlsx::build([['name' => 'Sheet1', 'rows' => $rows]]));
    }

    private function uploadAndPreview($user, string $type, UploadedFile $file)
    {
        $response = $this->actingAs($user)->post(route('import.upload'), ['type' => $type, 'file' => $file]);
        $response->assertRedirect();

        return $this->actingAs($user)->get($response->headers->get('Location'));
    }

    public function test_template_is_a_readable_xlsx_with_all_headers(): void
    {
        $office = $this->userWithRole(Role::Office);
        $response = $this->actingAs($office)->get(route('import.template', 'students'))->assertOk();
        $this->assertSame('application/vnd.openxmlformats-officedocument.spreadsheetml.sheet', $response->headers->get('content-type'));

        $path = tempnam(sys_get_temp_dir(), 't');
        file_put_contents($path, $response->getContent());
        $rows = Xlsx::read($path);
        unlink($path);

        $this->assertSame('نام *', $rows[0][1]);
        $this->assertContains('نام سرپرست', $rows[0]);
        $this->assertCount(1, $rows);   // فقط سربرگ؛ مثال در برگه‌ی راهنما
    }

    public function test_students_are_checked_previewed_and_imported(): void
    {
        $year = $this->makeYear();
        $section = $this->makeSection($year, 7);
        Student::factory()->create(['first_name' => 'تکراری', 'last_name' => 'قدیمی', 'father_name' => 'پدر']);
        $office = $this->userWithRole(Role::Office);

        $file = $this->xlsx([
            self::STUDENT_HEADER,
            ['', 'احمد', 'احمدی', 'محمد', 'پسر', '۱۳۹۲/۰۵/۱۰', '799123456', '7', 'الف', 'محمد احمدی', 'پدر'],
            ['A-90', 'مریم', '', 'کریم', 'دختر', '', '', '', '', '', ''],
            ['', 'بی‌جنس', '', 'پدر', 'نامعلوم', '1392/13/40', '', '', '', '', ''],
            ['', 'تکراری', 'قدیمی', 'پدر', 'پسر', '', '', '', '', '', ''],
            ['', '', '', '', '', '', '', '', '', '', ''],
            ['', 'شاگرد', '', 'پدر', 'پسر', '', '', 'صنف نهم', '', '', ''],
        ]);

        $this->uploadAndPreview($office, 'students', $file)
            ->assertOk()
            ->assertSee(__('import.summary', ['ok' => '۲', 'error' => '۲', 'duplicate' => '۱']))
            ->assertSee(__('import.bad_gender_student'))
            ->assertSee(__('import.duplicate_in_db'))
            ->assertSee(__('import.section_not_found', ['grade' => 'صنف نهم', 'section' => '—']));   // صنف نهم در سال جاری بخشی ندارد

        $this->assertSame(1, Student::count(), 'preview must not save');

        $this->actingAs($office)->post(route('import.store'), ['type' => 'students', 'token' => basename(Storage::disk('local')->files('imports')[0])])
            ->assertRedirect(route('students.index'))
            ->assertSessionHas('success');

        $ahmad = Student::where('first_name', 'احمد')->sole();
        $this->assertSame('0799123456', $ahmad->phone);
        $this->assertSame(Gender::Male, $ahmad->gender);
        $this->assertSame('2013-08-01', $ahmad->birth_date->format('Y-m-d'));
        $this->assertSame('محمد احمدی', $ahmad->guardians->first()->name);
        $this->assertSame($section->id, $ahmad->currentEnrollment->section_id);
        $this->assertSame('A-90', Student::where('first_name', 'مریم')->value('admission_no'));
        $this->assertSame(3, Student::count());
        $this->assertSame(['imported' => 2, 'enrolled' => 1, 'skipped' => 3], ActivityLog::where('action', 'import.students')->sole()->new_values);
        $this->assertSame([], Storage::disk('local')->files('imports'));
    }

    public function test_missing_required_columns_are_reported(): void
    {
        $this->uploadAndPreview($this->userWithRole(Role::Office), 'students', $this->xlsx([['نام', 'تخلص'], ['علی', 'رضایی']]))
            ->assertOk()->assertSee('نام پدر')->assertSee('جنسیت')->assertDontSee(__('import.confirm', ['count' => '۱']));
    }

    public function test_staff_import_from_csv_with_bom(): void
    {
        $csv = "\xEF\xBB\xBF"."نوع *,نام *,تخلص,نام پدر,جنسیت *,تحصیلات,تاریخ استخدام\n"
            ."معلم,حمید,رحیمی,رحیم,مرد,لیسانس,1398/07/01\n"
            ."کارمند خدماتی,گل,,,زن,,\n"
            ."راننده,بی‌نوع,,,مرد,,\n";
        $office = $this->userWithRole(Role::Office);

        $this->uploadAndPreview($office, 'staff', UploadedFile::fake()->createWithContent('staff.csv', $csv))
            ->assertOk()->assertSee(__('import.bad_staff_type'));
        $this->actingAs($office)->post(route('import.store'), ['type' => 'staff', 'token' => basename(Storage::disk('local')->files('imports')[0])])
            ->assertRedirect(route('staff.index'));

        $hamid = Staff::where('first_name', 'حمید')->sole();
        $this->assertSame(StaffType::Teacher, $hamid->staff_type);
        $this->assertSame(EducationLevel::Bachelor, $hamid->education);
        $this->assertSame(StaffType::Service, Staff::where('first_name', 'گل')->value('staff_type'));
        $this->assertSame(2, Staff::count());
    }

    public function test_broken_file_and_permissions(): void
    {
        $office = $this->userWithRole(Role::Office);
        $this->actingAs($office)->post(route('import.upload'), ['type' => 'students', 'file' => UploadedFile::fake()->createWithContent('x.xlsx', 'not a zip')]);
        $this->actingAs($office)->get(route('import.preview', ['type' => 'students', 'token' => basename(Storage::disk('local')->files('imports')[0])]))
            ->assertRedirect(route('import.index', ['type' => 'students']))->assertSessionHas('error', __('import.bad_file'));

        $this->actingAs($office)->post(route('import.upload'), ['type' => 'students', 'file' => UploadedFile::fake()->create('x.pdf', 10)])
            ->assertSessionHasErrors('file');

        $teacher = $this->userWithRole(Role::Teacher);
        $this->actingAs($teacher)->get(route('import.index'))->assertForbidden();
        $this->actingAs($teacher)->get(route('import.template', 'students'))->assertForbidden();
        $this->actingAs($teacher)->post(route('import.upload'), ['type' => 'staff', 'file' => $this->xlsx([['نام']])])->assertForbidden();
        $this->actingAs($office)->get(route('import.preview', ['type' => 'students', 'token' => '../../.env']))->assertRedirect();
    }
}
