<?php

namespace Tests\Feature;

use App\Enums\Role;
use App\Models\Staff;
use App\Models\Student;
use App\Models\User;
use App\Support\PersianText;
use Database\Seeders\RolePermissionSeeder;
use Tests\TestCase;

/**
 * 1.8.1: جستجو در همه‌ی فهرست‌ها یک‌سان کار کند — نیم‌فاصله مهم نیست، «ي/ك» عربی = «ی/ک»،
 * ارقام فارسی = لاتین، چند واژه به هر ترتیب، و % یا _ ورودی معنی خاص ندارد.
 */
class SearchTest extends TestCase
{
    private User $admin;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(RolePermissionSeeder::class);
        $this->admin = $this->userWithRole(Role::Admin);
    }

    public function test_search_terms_ignore_half_space_arabic_letters_and_digits(): void
    {
        $this->assertSame(['کتابها', 'علی', '125'], PersianText::searchTerms(" کتاب\u{200C}ها  علي ۱۲۵ "));
        $this->assertSame(['احمد', 'کریمی'], PersianText::searchTerms('احمد، کریمی'));
        $this->assertSame([], PersianText::searchTerms("  \u{200C} "));
    }

    public function test_student_list_search(): void
    {
        $target = Student::factory()->create(['first_name' => 'عبدالله', 'last_name' => 'نیک‌زاد', 'father_name' => 'محمدعلی', 'admission_no' => '4521']);
        Student::factory()->create(['first_name' => 'فریدون', 'last_name' => 'صدیقی', 'father_name' => 'نادر', 'admission_no' => '7788']);

        foreach (['نیکزاد', 'نيك‌زاد', 'عبدالله محمدعلی', 'محمدعلی عبدالله', '۴۵۲۱', 'نیک‌زاد عبدالله'] as $q) {
            $this->actingAs($this->admin)->get(route('students.index', ['q' => $q]))->assertOk()
                ->assertSee('عبدالله')->assertDontSee('فریدون');
        }
        $this->actingAs($this->admin)->get(route('students.index', ['q' => '%']))->assertOk()->assertDontSee('عبدالله');
        $this->assertSame($target->id, Student::query()->search('نیکزاد')->value('id'));
    }

    public function test_staff_and_user_list_search(): void
    {
        Staff::factory()->create(['first_name' => 'گل‌احمد', 'last_name' => 'یوسفی', 'specialization' => 'کیمیا']);
        Staff::factory()->create(['first_name' => 'نورالحق', 'last_name' => 'رحمانی', 'specialization' => 'فزیک']);
        foreach (['گلاحمد', 'یوسفي کیمیا'] as $q) {
            $this->actingAs($this->admin)->get(route('staff.index', ['q' => $q]))->assertOk()->assertSee('یوسفی')->assertDontSee('رحمانی');
        }

        User::factory()->create(['name' => 'فاطمه حیدری', 'username' => 'f.haidari']);
        User::factory()->create(['name' => 'زرغونه امیری', 'username' => 'z.amiri']);
        foreach (['حیدری', 'فاطمه حیدری', 'haidari', 'حيدري'] as $q) {
            $this->actingAs($this->admin)->get(route('settings.users.index', ['q' => $q]))->assertOk()->assertSee('فاطمه حیدری')->assertDontSee('زرغونه');
        }
    }
}
