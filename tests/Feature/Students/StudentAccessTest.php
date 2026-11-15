<?php

namespace Tests\Feature\Students;

use App\Enums\Role;
use App\Models\Student;
use App\Models\User;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use PHPUnit\Framework\Attributes\DataProvider;
use Tests\TestCase;

class StudentAccessTest extends TestCase
{
    public static function viewOnlyRoles(): array
    {
        return [
            'teacher' => [Role::Teacher],
            'accountant' => [Role::Accountant],
            'course manager' => [Role::CourseManager],
        ];
    }

    /** نقش‌هایی که همه‌ی دانش‌آموزان را با مشخصات پایه می‌بینند (students.view_all) */
    public static function rolesSeeingAllStudents(): array
    {
        return ['accountant' => [Role::Accountant], 'course manager' => [Role::CourseManager]];
    }

    #[DataProvider('rolesSeeingAllStudents')]
    public function test_view_only_roles_see_basic_info_but_not_sensitive_data(Role $role): void
    {
        $student = Student::factory()->create(['first_name' => 'فرهاد', 'phone' => '0799555444', 'tazkira_no' => 'SECRET-1', 'address' => 'آدرس محرمانه']);
        $user = $this->userWithRole($role);

        $this->actingAs($user)->get('/students')
            ->assertOk()
            ->assertSee('فرهاد')
            ->assertDontSee('0799555444')
            ->assertDontSee(__('students.create'));

        $this->actingAs($user)->get("/students/{$student->id}")
            ->assertOk()
            ->assertSee('فرهاد')
            ->assertSee(__('students.limited_view'))
            ->assertDontSee('0799555444')
            ->assertDontSee('SECRET-1')
            ->assertDontSee('آدرس محرمانه')
            ->assertDontSee('guardians-pane', false);
    }

    #[DataProvider('viewOnlyRoles')]
    public function test_view_only_roles_cannot_change_anything(Role $role): void
    {
        Storage::fake('local');
        $student = Student::factory()->create();
        $user = $this->userWithRole($role);

        $this->actingAs($user)->get('/students/create')->assertForbidden();
        $this->actingAs($user)->post('/students', ['first_name' => 'x'])->assertForbidden();
        $this->actingAs($user)->get("/students/{$student->id}/edit")->assertForbidden();
        $this->actingAs($user)->put("/students/{$student->id}", ['first_name' => 'x'])->assertForbidden();
        $this->actingAs($user)->patch("/students/{$student->id}/status", ['status' => 'inactive', 'reason' => 'x'])->assertForbidden();
        $this->actingAs($user)->delete("/students/{$student->id}", ['reason' => 'x'])->assertForbidden();
        $this->actingAs($user)->post("/students/{$student->id}/guardians", ['name' => 'x', 'relation' => 'پدر'])->assertForbidden();
        $this->actingAs($user)->post("/students/{$student->id}/documents", ['title' => 'x', 'file' => UploadedFile::fake()->create('a.pdf', 10, 'application/pdf')])->assertForbidden();
        $this->actingAs($user)->get('/students?status=deleted')->assertOk(); // فیلتر نادیده گرفته می‌شود
    }

    public function test_deleted_students_are_hidden_from_view_only_roles(): void
    {
        $student = Student::factory()->create(['first_name' => 'حذفی']);
        $student->delete();
        $teacher = $this->userWithRole(Role::Teacher);

        $this->actingAs($teacher)->get("/students/{$student->id}")->assertForbidden();
        $this->actingAs($teacher)->get('/students?status=deleted')->assertDontSee('حذفی');
    }

    public function test_principal_can_manage_students(): void
    {
        $principal = $this->userWithRole(Role::Principal);

        $this->actingAs($principal)->get('/students/create')->assertOk()->assertSee(__('students.create'));
    }

    public function test_photo_requires_permission(): void
    {
        Storage::fake('local');
        $student = Student::factory()->create(['photo_path' => UploadedFile::fake()->image('p.jpg')->store('students/photos', 'local')]);

        $this->get("/students/{$student->id}/photo")->assertRedirect('/login');
        $this->actingAs($this->userWithRole(Role::Accountant))->get("/students/{$student->id}/photo")->assertOk();
        // معلمی که این دانش‌آموز در صنف‌هایش نیست
        $this->actingAs($this->userWithRole(Role::Teacher))->get("/students/{$student->id}/photo")->assertForbidden();

        // کاربری بدون students.view (مثلاً نقشی ساختگی بدون مجوز)
        $this->actingAs(User::factory()->create())->get("/students/{$student->id}/photo")->assertForbidden();
    }

    public function test_private_files_are_not_reachable_through_public_folder(): void
    {
        $this->assertFalse(is_dir(public_path('storage')), 'public/storage link must not exist');
        $this->get('/storage/students/photos/x.jpg')->assertNotFound();
    }
}
