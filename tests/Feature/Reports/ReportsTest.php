<?php

namespace Tests\Feature\Reports;

use App\Enums\Role;
use App\Models\FinanceCategory;
use App\Models\Setting;
use App\Services\AttendanceService;
use Carbon\CarbonImmutable;
use Tests\Feature\Exams\ExamTestCase;

/** گزارش‌های مرحله‌ی ۹ و داشبورد (زمینه: بخش با سه دانش‌آموز و نمرات ExamTestCase). */
class ReportsTest extends ExamTestCase
{
    public function test_student_statistics_list_print_and_csv(): void
    {
        $html = $this->actingAs($this->principal)->get(route('reports.students.index', ['list' => 1]))->assertOk()->getContent();
        $this->assertStringContainsString(__('reports.by_grade'), $html);
        $this->assertStringContainsString('شاگرداول', $html);
        $this->assertStringContainsString('بیگانه', $html); // بخش دوم هم برای مدیر

        $this->actingAs($this->principal)->get(route('reports.students.index', ['print' => 1]))->assertOk();
        $csv = $this->actingAs($this->principal)->get(route('reports.students.export', ['section' => $this->section->id]))->assertOk()->streamedContent();
        $this->assertStringStartsWith("\xEF\xBB\xBF", $csv);
        $this->assertStringContainsString('شاگرددوم', $csv);
        $this->assertStringNotContainsString('بیگانه', $csv);
    }

    public function test_teacher_sees_only_own_sections(): void
    {
        $this->actingAs($this->teacher)->get(route('reports.students.index', ['list' => 1]))->assertOk()->assertSee('شاگرداول')->assertDontSee('بیگانه');
        $this->actingAs($this->teacher)->get(route('reports.students.index', ['section' => $this->otherSection->id]))->assertForbidden();
        $this->actingAs($this->teacher)->get(route('reports.finance.index'))->assertForbidden();
        $this->actingAs($this->userWithRole(Role::Accountant))->get(route('reports.students.index'))->assertForbidden();
    }

    public function test_attendance_summary_by_section(): void
    {
        $attendance = app(AttendanceService::class);
        $statuses = [];
        foreach ($this->students as $i => $e) {
            $statuses[$e->id] = $i === 0 ? 'absent' : 'present';
        }
        $attendance->saveSection($this->section, CarbonImmutable::parse('2026-11-15'), $statuses, [], $this->principal);

        $html = $this->actingAs($this->principal)->get(route('reports.attendance.index', ['from' => '۱۴۰۵/۰۸/۰۱', 'to' => '۱۴۰۵/۰۸/۳۰']))->assertOk()->getContent();
        $this->assertStringContainsString('۶۶.۷٪', $html); // ۲ از ۳

        $csv = $this->actingAs($this->principal)->get(route('reports.attendance.export', ['section' => $this->section->id]))->assertOk()->streamedContent();
        $this->assertStringContainsString('66.7', $csv);
    }

    public function test_results_summary_lists_failed_and_conditional(): void
    {
        $this->enterAllMarks();

        $this->actingAs($this->principal)->get(route('reports.results.index', ['section' => $this->section->id]))
            ->assertOk()
            ->assertSee('شاگرددوم')   // مشروط
            ->assertSee('شاگردسوم')   // ناکام
            ->assertSee('۳۳.۳٪');     // ۱ کامیاب از ۳ نتیجه‌ی کامل
        $csv = $this->actingAs($this->principal)->get(route('reports.results.export'))->assertOk()->streamedContent();
        $this->assertStringContainsString('ریاضی', $csv);
    }

    public function test_finance_summary_separates_school_and_courses(): void
    {
        Setting::put('school_ledger_enabled', '1');
        $accountant = $this->userWithRole(Role::Accountant);
        $this->actingAs($accountant)->post(route('finance.ledger.store'), [
            'type' => 'income', 'finance_category_id' => FinanceCategory::create(['type' => 'income', 'name' => 'کمک آزمایشی', 'is_active' => true])->id,
            'amount' => '2500', 'date' => '۱۴۰۵/۰۸/۲۰',
        ])->assertSessionHasNoErrors();

        $this->actingAs($accountant)->get(route('reports.finance.index'))->assertOk()
            ->assertSee(__('reports.school_part'))->assertSee(__('reports.courses_part'))->assertSee('۲٬۵۰۰');
        $csv = $this->actingAs($accountant)->get(route('reports.finance.export'))->assertOk()->streamedContent();
        $this->assertStringContainsString(',2500', $csv);
        $this->assertStringNotContainsString(__('finance.sum_fees'), $csv); // فیس خاموش

        // مسئول کورس‌ها فقط بخش کورس‌ها
        $this->actingAs($this->userWithRole(Role::CourseManager))->get(route('reports.finance.index'))->assertOk()
            ->assertSee(__('reports.courses_part'))->assertSee(__('reports.school_off'));
        $this->actingAs($this->userWithRole(Role::Office))->get(route('reports.finance.index'))->assertForbidden();
    }

    public function test_dashboard_cards_follow_permissions(): void
    {
        $this->actingAs($this->principal)->get('/')->assertOk()
            ->assertSee(__('dashboard.students'))->assertSee(__('dashboard.attendance_today'))->assertSee(__('dashboard.exams'))
            ->assertSee('امتحان سالانه');

        $this->actingAs($this->teacher)->get('/')->assertOk()
            ->assertSee(__('dashboard.my_students'))->assertDontSee(__('dashboard.debt'));

        Setting::put('fees_enabled', '1');
        $this->actingAs($this->userWithRole(Role::Accountant))->get('/')->assertOk()
            ->assertSee(__('dashboard.debt'))->assertSee(__('dashboard.course_cash'))->assertDontSee(__('dashboard.attendance_today'));

        $this->actingAs($this->userWithRole(Role::CourseManager))->get('/')->assertOk()->assertSee(__('dashboard.courses_active'));
    }
}
