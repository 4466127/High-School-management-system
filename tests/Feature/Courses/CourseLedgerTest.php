<?php

namespace Tests\Feature\Courses;

use App\Models\ActivityLog;
use App\Models\CourseLedgerEntry;
use App\Models\Staff;

/** دفتر مالی کورس: کمک به صندوق و مستقیم به معلم، معاش، مصرف، مانده، باطل کردن. */
class CourseLedgerTest extends CourseTestCase
{
    private function entry(array $data)
    {
        return $this->actingAs($this->accountant)->post(route('courses.ledger.store', $this->course), $data + [
            'channel' => 'cashbox', 'date' => '۱۴۰۵/۰۸/۲۰',
        ]);
    }

    public function test_balance_counts_cashbox_only_and_teacher_received(): void
    {
        $this->entry(['entry_type' => 'donation', 'amount' => '۱۰٬۰۰۰', 'donor_name' => 'حاجی عبدالله'])->assertSessionHasNoErrors();
        $this->entry(['entry_type' => 'donation', 'amount' => '3000', 'channel' => 'direct_to_teacher', 'staff_id' => $this->otherStaff->id])->assertSessionHasNoErrors();
        $this->entry(['entry_type' => 'teacher_salary', 'amount' => '4000', 'staff_id' => $this->teacherStaff->id])->assertSessionHasNoErrors();
        $this->entry(['entry_type' => 'expense', 'amount' => '1500', 'description' => 'کتاب'])->assertSessionHasNoErrors();

        $html = $this->actingAs($this->accountant)->get(route('courses.ledger.show', $this->course))->assertOk()->getContent();
        $this->assertStringContainsString('۱۳٬۰۰۰', $html);  // مجموع کمک‌ها
        $this->assertStringContainsString('۴٬۵۰۰', $html);   // مانده‌ی صندوق: ۱۰۰۰۰ − ۴۰۰۰ − ۱۵۰۰
        $this->assertStringContainsString($this->otherStaff->full_name, $html);
        $this->assertTrue(ActivityLog::where('action', 'course.ledger_recorded')->exists());

        $this->actingAs($this->accountant)->get(route('courses.ledger.index'))->assertOk()->assertSee('۴٬۵۰۰');
        $this->actingAs($this->accountant)->get(route('courses.ledger.show', [$this->course, 'print' => 1]))->assertOk();

        $csv = $this->actingAs($this->accountant)->get(route('courses.ledger.export', $this->course))->assertOk()->streamedContent();
        $this->assertStringContainsString('حاجی عبدالله', $csv);
    }

    public function test_rules_for_teacher_channel_and_balance(): void
    {
        // معاش بدون معلم یا با معلم بیرون از کورس
        $this->entry(['entry_type' => 'teacher_salary', 'amount' => '100'])->assertSessionHasErrors('staff_id');
        $stranger = Staff::factory()->create();
        $this->entry(['entry_type' => 'donation', 'amount' => '100', 'channel' => 'direct_to_teacher', 'staff_id' => $stranger->id])->assertSessionHasErrors('staff_id');
        // «مستقیم به معلم» فقط برای کمک
        $this->entry(['entry_type' => 'expense', 'amount' => '100', 'channel' => 'direct_to_teacher'])->assertSessionHasErrors('channel');
        // معاش بیشتر از مانده‌ی صندوق
        $this->entry(['entry_type' => 'donation', 'amount' => '1000'])->assertSessionHasNoErrors();
        $this->entry(['entry_type' => 'teacher_salary', 'amount' => '1500', 'staff_id' => $this->teacherStaff->id])->assertSessionHasErrors('amount');
        // تاریخ آینده
        $this->entry(['entry_type' => 'donation', 'amount' => '1000', 'date' => '۱۴۰۵/۰۹/۲۰'])->assertSessionHasErrors('date');

        $this->assertSame(1, CourseLedgerEntry::count());
    }

    public function test_void_needs_reason_and_keeps_balance_non_negative(): void
    {
        $this->entry(['entry_type' => 'donation', 'amount' => '1000']);
        $this->entry(['entry_type' => 'expense', 'amount' => '800']);
        [$donation, $expense] = CourseLedgerEntry::orderBy('id')->get()->all();

        $this->actingAs($this->accountant)->patch(route('courses.ledger.void', $donation), [])->assertSessionHasErrors('reason');
        $this->actingAs($this->accountant)->patch(route('courses.ledger.void', $donation), ['reason' => 'اشتباه'])
            ->assertSessionHas('error', __('courses.void_makes_negative'));
        $this->assertNull($donation->fresh()->voided_at);

        $this->actingAs($this->accountant)->patch(route('courses.ledger.void', $expense), ['reason' => 'دو بار ثبت شد'])->assertSessionHas('success');
        $this->actingAs($this->accountant)->patch(route('courses.ledger.void', $donation), ['reason' => 'اشتباه'])->assertSessionHas('success');
        $this->assertNotNull($donation->fresh()->voided_at);
        $this->assertSame(2, CourseLedgerEntry::count()); // حذف نمی‌شود
        $this->assertTrue(ActivityLog::where('action', 'course.ledger_voided')->exists());
    }

    public function test_only_ledger_permission_reaches_course_finance(): void
    {
        $this->actingAs($this->teacher)->get(route('courses.ledger.show', $this->course))->assertForbidden();
        $this->actingAs($this->teacher)->get(route('courses.ledger.index'))->assertForbidden();
        $this->actingAs($this->teacher)->post(route('courses.ledger.store', $this->course), ['entry_type' => 'donation', 'channel' => 'cashbox', 'amount' => '10', 'date' => '۱۴۰۵/۰۸/۲۰'])->assertForbidden();
        $this->actingAs($this->manager)->get(route('courses.ledger.show', $this->course))->assertOk();
    }
}
