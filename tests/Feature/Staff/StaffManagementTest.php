<?php

namespace Tests\Feature\Staff;

use App\Enums\EducationLevel;
use App\Enums\EmploymentStatus;
use App\Enums\Role;
use App\Enums\StaffType;
use App\Models\Role as RoleModel;
use App\Models\Staff;
use App\Models\User;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use PHPUnit\Framework\Attributes\DataProvider;
use Tests\TestCase;

class StaffManagementTest extends TestCase
{
    private User $office;

    protected function setUp(): void
    {
        parent::setUp();
        Storage::fake('local');
        $this->office = $this->userWithRole(Role::Office);
    }

    public function test_office_registers_teacher_with_photo(): void
    {
        $this->actingAs($this->office)->post('/staff', [
            'staff_type' => 'teacher',
            'first_name' => 'نورالله',
            'last_name' => 'حکیمی',
            'gender' => 'male',
            'specialization' => 'ریاضی',
            'hire_date' => '۱۴۰۰/۰۷/۰۱',
            'photo' => UploadedFile::fake()->image('t.jpg'),
        ])->assertRedirect();

        $member = Staff::where('first_name', 'نورالله')->firstOrFail();
        $this->assertSame(StaffType::Teacher, $member->staff_type);
        $this->assertSame(EmploymentStatus::Active, $member->employment_status);
        $this->assertSame('2021-09-23', $member->hire_date->toDateString());
        Storage::disk('local')->assertExists($member->photo_path);
        $this->assertDatabaseHas('activity_logs', ['action' => 'staff.created', 'subject_id' => $member->id]);

        $this->actingAs($this->office)->get("/staff/{$member->id}")->assertOk()->assertSee('نورالله حکیمی')->assertSee('۱ میزان ۱۴۰۰');
    }

    public function test_education_is_chosen_from_fixed_list(): void
    {
        $base = ['staff_type' => 'teacher', 'first_name' => 'آزمایش', 'gender' => 'female'];

        $this->actingAs($this->office)->post('/staff', $base + ['education' => 'هر متنی'])
            ->assertSessionHasErrors('education');

        $this->actingAs($this->office)->post('/staff', $base + ['education' => 'master'])->assertRedirect();
        $member = Staff::where('first_name', 'آزمایش')->firstOrFail();

        $this->assertSame(EducationLevel::Master, $member->education);
        $this->actingAs($this->office)->get("/staff/{$member->id}")->assertSee('کارشناسی ارشد (ماستری)')->assertSee('زن');
        $this->actingAs($this->office)->get('/staff/create')
            ->assertSee('دوازده‌پاس')->assertSee('چهارده‌پاس')->assertSee('کارشناسی (لیسانس)')->assertSee('دکترا');
    }

    public function test_list_filters_by_type_and_search(): void
    {
        Staff::factory()->create(['first_name' => 'معلم‌یک', 'staff_type' => StaffType::Teacher]);
        Staff::factory()->ofType(StaffType::Service)->create(['first_name' => 'محافظ‌یک']);

        $this->actingAs($this->office)->get('/staff?type=service')->assertSee('محافظ‌یک')->assertDontSee('معلم‌یک');
        $this->actingAs($this->office)->get('/staff?q='.urlencode('معلم‌یک'))->assertSee('معلم‌یک')->assertDontSee('محافظ‌یک');
    }

    public function test_work_history_is_added_validated_and_removed(): void
    {
        $member = Staff::factory()->create();

        $this->actingAs($this->office)->post("/staff/{$member->id}/history", [
            'title' => 'معلم', 'from_date' => '1400/01/01', 'to_date' => '1399/01/01',
        ])->assertSessionHasErrors(['to_date' => __('staff.to_before_from')]);

        $this->actingAs($this->office)->post("/staff/{$member->id}/history", [
            'title' => 'معلم کیمیا', 'organization' => 'لیسه‌ی نمونه', 'from_date' => '1395/07/01', 'to_date' => '1399/06/31',
        ])->assertRedirect();

        $entry = $member->workHistory()->firstOrFail();
        $this->assertSame('2016-09-22', $entry->from_date->toDateString());

        $other = Staff::factory()->create();
        $this->actingAs($this->office)->delete("/staff/{$other->id}/history/{$entry->id}")->assertNotFound();

        $this->actingAs($this->office)->delete("/staff/{$member->id}/history/{$entry->id}")->assertRedirect();
        $this->assertModelMissing($entry);
        $this->assertDatabaseHas('activity_logs', ['action' => 'staff.work_history_removed', 'subject_id' => $member->id]);
    }

    public function test_marking_staff_as_left_deactivates_linked_user(): void
    {
        $member = Staff::factory()->create();
        $account = User::factory()->withRole(Role::Teacher)->create(['staff_id' => $member->id]);

        $this->actingAs($this->office)->patch("/staff/{$member->id}/status", ['employment_status' => 'left'])
            ->assertSessionHasErrors('reason');

        $this->actingAs($this->office)->patch("/staff/{$member->id}/status", ['employment_status' => 'left', 'reason' => 'تبدیلی'])
            ->assertRedirect();

        $this->assertSame(EmploymentStatus::Left, $member->fresh()->employment_status);
        $this->assertFalse($account->fresh()->is_active);
        $this->assertDatabaseHas('activity_logs', ['action' => 'user.deactivated', 'subject_id' => $account->id]);
    }

    public function test_soft_delete_and_restore(): void
    {
        $member = Staff::factory()->create(['first_name' => 'اشتباهی']);

        $this->actingAs($this->office)->delete("/staff/{$member->id}", ['reason' => 'اشتباه ثبت شد'])->assertRedirect(route('staff.index'));
        $this->assertSoftDeleted($member);
        $this->actingAs($this->office)->get('/staff?status=all')->assertDontSee('href="'.route('staff.show', $member).'"', false);
        $this->actingAs($this->office)->get("/staff/{$member->id}/edit")->assertNotFound();

        $this->actingAs($this->office)->patch("/staff/{$member->id}/restore")->assertRedirect();
        $this->assertNotSoftDeleted($member);
    }

    public static function rolesWithoutStaffAccess(): array
    {
        return [[Role::Teacher], [Role::Accountant], [Role::CourseManager]];
    }

    #[DataProvider('rolesWithoutStaffAccess')]
    public function test_other_roles_cannot_reach_staff_pages(Role $role): void
    {
        $member = Staff::factory()->create();
        $user = $this->userWithRole($role);

        $this->actingAs($user)->get('/staff')->assertForbidden();
        $this->actingAs($user)->get("/staff/{$member->id}")->assertForbidden();
        $this->actingAs($user)->post('/staff', ['first_name' => 'x'])->assertForbidden();
        $this->actingAs($user)->get("/staff/{$member->id}/photo")->assertForbidden();
    }

    public function test_admin_links_user_account_to_staff_member(): void
    {
        $admin = $this->userWithRole(Role::Admin);
        $member = Staff::factory()->create();
        $teacherRole = RoleModel::where('key', 'teacher')->value('id');

        $this->actingAs($admin)->post('/settings/users', [
            'name' => $member->full_name, 'username' => 'teacher.one', 'staff_id' => $member->id,
            'password' => 'initial-pass-1', 'password_confirmation' => 'initial-pass-1', 'roles' => [$teacherRole],
        ])->assertRedirect();

        $this->assertSame($member->id, User::where('username', 'teacher.one')->value('staff_id'));
        $this->actingAs($admin)->get("/staff/{$member->id}")->assertOk()->assertSee('teacher.one');

        // یک کارمند فقط یک حساب
        $this->actingAs($admin)->post('/settings/users', [
            'name' => 'x', 'username' => 'teacher.two', 'staff_id' => $member->id,
            'password' => 'initial-pass-1', 'password_confirmation' => 'initial-pass-1', 'roles' => [$teacherRole],
        ])->assertSessionHasErrors('staff_id');
    }
}
