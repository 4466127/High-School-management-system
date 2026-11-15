<?php

namespace Tests\Feature\Students;

use App\Enums\Role;
use App\Enums\StudentStatus;
use App\Models\ActivityLog;
use App\Models\Student;
use App\Models\User;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

class StudentManagementTest extends TestCase
{
    private User $office;

    protected function setUp(): void
    {
        parent::setUp();
        Storage::fake('local');
        $this->office = $this->userWithRole(Role::Office);
    }

    private function validData(array $overrides = []): array
    {
        return array_merge([
            'first_name' => 'احمد',
            'last_name' => 'کریمی',
            'father_name' => 'عبدالله',
            'grandfather_name' => 'غلام',
            'gender' => 'male',
            'birth_date' => '۱۳۹۵/۰۳/۱۵',
            'phone' => '۰۷۹۹ ۱۲۳ ۴۵۶',
            'province' => 'کابل',
            'admission_date' => '1405/01/10',
        ], $overrides);
    }

    public function test_office_registers_student_with_guardian_photo_and_auto_admission_no(): void
    {
        Student::factory()->create(['admission_no' => '41']);

        $response = $this->actingAs($this->office)->post('/students', $this->validData([
            'photo' => UploadedFile::fake()->image('photo.jpg', 300, 300),
            'guardian' => ['name' => 'عبدالله کریمی', 'relation' => 'پدر', 'phone' => '0700111222'],
        ]));

        $student = Student::latest('id')->firstOrFail(); // نه با نام: factory ممکن است تصادفی همین نام را بدهد
        $this->assertSame('احمد', $student->first_name);
        $response->assertRedirect(route('students.show', $student));

        $this->assertSame('42', $student->admission_no);
        $this->assertSame('2016-06-04', $student->birth_date->toDateString(), 'Jalali 1395/03/15 must be stored as Gregorian');
        $this->assertSame('0799 123 456', $student->phone, 'Persian digits must be normalized');
        $this->assertSame(StudentStatus::Active, $student->status);
        Storage::disk('local')->assertExists($student->photo_path);
        $this->assertStringStartsWith('students/photos/', $student->photo_path);

        $guardian = $student->guardians()->firstOrFail();
        $this->assertSame('عبدالله کریمی', $guardian->name);
        $this->assertTrue((bool) $guardian->pivot->is_primary);

        $this->assertDatabaseHas('activity_logs', ['action' => 'student.created', 'subject_id' => $student->id, 'user_id' => $this->office->id]);
    }

    public function test_manual_admission_no_must_be_unique_including_deleted(): void
    {
        $deleted = Student::factory()->create(['admission_no' => '500']);
        $deleted->delete();

        $this->actingAs($this->office)->post('/students', $this->validData(['admission_no' => '500']))
            ->assertSessionHasErrors('admission_no');
    }

    public function test_validation_messages_are_persian(): void
    {
        $this->actingAs($this->office)->from('/students/create')->post('/students', [
            'gender' => 'x',
            'birth_date' => '1404/12/30',
            'phone' => 'abc',
        ])->assertSessionHasErrors([
            'first_name' => 'نام لازم است.',
            'father_name' => 'نام پدر لازم است.',
            'birth_date' => 'تاریخ تولد باید تاریخ شمسی معتبر باشد، مثلاً ۱۴۰۵/۰۸/۲۴.',
        ])->assertSessionHasErrors(['gender', 'phone']);
    }

    public function test_birth_date_cannot_be_in_future(): void
    {
        $this->actingAs($this->office)->post('/students', $this->validData(['birth_date' => '1490/01/01']))
            ->assertSessionHasErrors(['birth_date' => 'تاریخ تولد نمی‌تواند در آینده باشد.']);
    }

    public function test_update_logs_old_and_new_values(): void
    {
        $student = Student::factory()->create(['first_name' => 'نوید', 'birth_date' => '2015-01-01']);

        $this->actingAs($this->office)->put("/students/{$student->id}", $this->validData([
            'admission_no' => $student->admission_no,
            'first_name' => 'نوید احمد',
            'birth_date' => '1393/10/11', // = 2015-01-01 (بدون تغییر)
        ]))->assertRedirect(route('students.show', $student));

        $log = ActivityLog::where('action', 'student.updated')->latest('id')->firstOrFail();
        $this->assertSame('نوید', $log->old_values['first_name']);
        $this->assertSame('نوید احمد', $log->new_values['first_name']);
        $this->assertArrayNotHasKey('birth_date', $log->new_values, 'Unchanged date must not be logged as a change');
    }

    public function test_photo_can_be_replaced_and_old_file_is_removed(): void
    {
        $this->actingAs($this->office)->post('/students', $this->validData(['photo' => UploadedFile::fake()->image('a.jpg')]));
        $student = Student::latest('id')->firstOrFail();
        $oldPhoto = $student->photo_path;

        $this->actingAs($this->office)->put("/students/{$student->id}", $this->validData([
            'admission_no' => $student->admission_no,
            'photo' => UploadedFile::fake()->image('b.png'),
        ]));

        $student->refresh();
        $this->assertNotSame($oldPhoto, $student->photo_path);
        Storage::disk('local')->assertMissing($oldPhoto);
        Storage::disk('local')->assertExists($student->photo_path);

        $this->actingAs($this->office)->get("/students/{$student->id}/photo")->assertOk();
    }

    public function test_deactivate_requires_reason_and_is_logged(): void
    {
        $student = Student::factory()->create();

        $this->actingAs($this->office)->patch("/students/{$student->id}/status", ['status' => 'inactive'])
            ->assertSessionHasErrors('reason');

        $this->actingAs($this->office)->patch("/students/{$student->id}/status", ['status' => 'inactive', 'reason' => 'سفر خانواده', 'date' => '1405/08/20'])
            ->assertRedirect();

        $student->refresh();
        $this->assertSame(StudentStatus::Inactive, $student->status);
        $this->assertSame('سفر خانواده', $student->status_reason);
        $this->assertSame('2026-11-11', $student->status_changed_on->toDateString());
        $this->assertDatabaseHas('activity_logs', ['action' => 'student.status_changed', 'subject_id' => $student->id]);

        // انتقال/ترک تحصیل از این مسیر ممکن نیست (مرحله‌ی ۴)
        $this->actingAs($this->office)->patch("/students/{$student->id}/status", ['status' => 'dropped', 'reason' => 'x'])
            ->assertSessionHasErrors('status');
    }

    public function test_soft_delete_with_reason_and_restore(): void
    {
        $student = Student::factory()->create(['first_name' => 'تکراری']);

        $this->actingAs($this->office)->delete("/students/{$student->id}", [])->assertSessionHasErrors('reason');

        $this->actingAs($this->office)->delete("/students/{$student->id}", ['reason' => 'دو بار ثبت شده'])
            ->assertRedirect(route('students.index'));

        $this->assertSoftDeleted($student);
        $this->assertSame('دو بار ثبت شده', Student::withTrashed()->find($student->id)->deleted_reason);
        // (پیام موفقیت نام را دارد، پس لینک سطر جدول بررسی می‌شود)
        $link = 'href="'.route('students.show', $student).'"';
        $this->actingAs($this->office)->get('/students?status=all')->assertDontSee($link, false);
        $this->actingAs($this->office)->get('/students?status=deleted')->assertSee($link, false);

        $this->actingAs($this->office)->patch("/students/{$student->id}/restore")->assertRedirect(route('students.show', $student));
        $this->assertNotSoftDeleted($student);
        $this->assertDatabaseHas('activity_logs', ['action' => 'student.deleted', 'subject_id' => $student->id]);
        $this->assertDatabaseHas('activity_logs', ['action' => 'student.restored', 'subject_id' => $student->id]);
    }

    public function test_search_handles_arabic_letters_digits_and_multiple_words(): void
    {
        Student::factory()->create(['first_name' => 'علی', 'last_name' => 'کریمی', 'father_name' => 'یوسف', 'admission_no' => '1234']);
        Student::factory()->create(['first_name' => 'مریم', 'last_name' => 'نوری', 'father_name' => 'رحیم', 'admission_no' => '5678']);

        $search = fn (string $q) => $this->actingAs($this->office)->get('/students?status=all&q='.urlencode($q));

        $search('علي')->assertSee('علی کریمی')->assertDontSee('مریم نوری');          // ي عربی
        $search('كريمي')->assertSee('علی کریمی');                                    // ك و ي عربی
        $search('علی یوسف')->assertSee('علی کریمی')->assertDontSee('مریم نوری');      // نام + نام پدر
        $search('۵۶۷۸')->assertSee('مریم نوری')->assertDontSee('علی کریمی');          // نمبر اساس با ارقام فارسی
        $search('رحیم')->assertSee('مریم نوری');
    }

    public function test_arabic_letters_are_stored_as_persian(): void
    {
        $this->actingAs($this->office)->post('/students', $this->validData(['first_name' => 'علي', 'father_name' => 'موسى']));

        $this->assertDatabaseHas('students', ['first_name' => 'علی', 'father_name' => 'موسی']);
    }

    public function test_profile_shows_jalali_dates_and_tabs(): void
    {
        $student = Student::factory()->create(['birth_date' => '2016-06-04', 'tazkira_no' => 'T-555']);

        $this->actingAs($this->office)->get("/students/{$student->id}")
            ->assertOk()
            ->assertSee('۱۵ جوزا ۱۳۹۵')
            ->assertSee('T-555')
            ->assertSee('سرپرستان')
            ->assertSee('اسناد');
    }
}
