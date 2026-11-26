<?php

namespace Tests\Feature\Teaching;

use App\Enums\PlanItemStatus;
use App\Enums\PlanStatus;
use App\Enums\Role;
use App\Models\ActivityLog;
use App\Models\Staff;
use App\Models\StaffAttendance;
use App\Models\TeachingPlan;
use App\Models\TeachingPlanItem;
use Database\Seeders\DemoTeachingSeeder;
use Database\Seeders\RolePermissionSeeder;
use Tests\Feature\Exams\ExamTestCase;

/** مرحله‌ی ۱۶: پلان تدریس (گردش منظوری)، پیشرفت، کاپی، دسترسی، گزارش کار معلمان */
class TeachingPlanTest extends ExamTestCase
{
    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(RolePermissionSeeder::class);
    }

    private function openPlan($user = null): TeachingPlan
    {
        $this->actingAs($user ?? $this->teacher)->post(route('teaching.plans.open'), ['section' => $this->section->id, 'subject' => $this->math->id])->assertRedirect();

        return TeachingPlan::where('section_id', $this->section->id)->where('subject_id', $this->math->id)->firstOrFail();
    }

    public function test_teacher_creates_plan_adds_topics_and_submits(): void
    {
        $this->actingAs($this->teacher)->get(route('teaching.plans.index'))->assertOk()->assertSee($this->math->name)->assertSee(__('teaching.create'));
        $plan = $this->openPlan();
        $this->assertSame($this->teacher->staff_id, $plan->staff_id);
        $this->assertSame(PlanStatus::Draft, $plan->status);
        $this->openPlan();
        $this->assertSame(1, TeachingPlan::count());

        $this->actingAs($this->teacher)->post(route('teaching.plans.topics', $plan), [
            'topics' => "۱. اعداد حقیقی\n2- معادلات\n\n• نامساوی‌ها", 'start_date' => '1405/01/01', 'hours' => 3,
        ])->assertSessionHasNoErrors();
        $items = $plan->items()->get();
        $this->assertSame(['اعداد حقیقی', 'معادلات', 'نامساوی‌ها'], $items->pluck('topic')->all());
        $this->assertSame([1, 2, 3], $items->pluck('week_no')->all());
        $this->assertSame('2026-03-28', $items[1]->planned_from->toDateString());
        $this->assertSame('2026-04-03', $items[1]->planned_to->toDateString());

        // هر سه هفته گذشته‌اند ← عقب‌مانده
        $this->assertSame(3, $plan->fresh()->progress()['overdue']);

        $this->actingAs($this->teacher)->post(route('teaching.plans.submit', $plan))->assertSessionHasNoErrors();
        $this->assertSame(PlanStatus::Submitted, $plan->fresh()->status);
        // قفل: افزودن یا حذف نمی‌شود
        $this->actingAs($this->teacher)->post(route('teaching.plans.topics', $plan), ['topics' => 'تازه'])->assertSessionHasErrors('plan');
        $this->actingAs($this->teacher)->delete(route('teaching.items.destroy', $items[0]))->assertSessionHasErrors('plan');
        $this->assertTrue(ActivityLog::where('action', 'teaching.plan_submitted')->exists());
    }

    public function test_progress_is_recorded_even_after_approval(): void
    {
        $plan = $this->openPlan();
        $this->actingAs($this->teacher)->post(route('teaching.plans.topics', $plan), ['topics' => "الف\nب\nج\nد"]);
        $this->actingAs($this->teacher)->post(route('teaching.plans.submit', $plan));

        // تأیید فقط مدیر؛ برگشت یادداشت می‌خواهد
        $this->actingAs($this->teacher)->post(route('teaching.plans.review', $plan), ['decision' => 'approve'])->assertForbidden();
        $this->actingAs($this->principal)->post(route('teaching.plans.review', $plan), ['decision' => 'return'])->assertSessionHasErrors('note');
        $this->actingAs($this->principal)->post(route('teaching.plans.review', $plan), ['decision' => 'approve', 'note' => 'خوب است'])->assertSessionHasNoErrors();
        $plan->refresh();
        $this->assertSame(PlanStatus::Approved, $plan->status);
        $this->assertSame($this->principal->id, $plan->reviewed_by);

        [$a, $b, $c] = $plan->items()->get()->all();
        $this->actingAs($this->teacher)->post(route('teaching.items.progress', $a), ['status' => 'done', 'completed_on' => '1405/08/20'])->assertSessionHasNoErrors();
        $this->actingAs($this->teacher)->post(route('teaching.items.progress', $b), ['status' => 'partial'])->assertSessionHasNoErrors();
        $this->actingAs($this->teacher)->post(route('teaching.items.progress', $c), ['status' => 'skipped'])->assertSessionHasNoErrors();
        $this->assertSame('2026-11-11', $a->fresh()->completed_on->toDateString());
        $this->assertSame(PlanItemStatus::Skipped, $c->fresh()->status);
        $this->assertNull($c->fresh()->completed_on);
        // ۳ موضوع شمرده (رها حساب نمی‌شود): (۱ + ۰٫۵) ÷ ۳ = ۵۰٪
        $this->assertSame(50, $plan->fresh()->progress()['percent']);

        // برگشت پس از منظوری ← دوباره قابل تغییر
        $this->actingAs($this->principal)->post(route('teaching.plans.review', $plan), ['decision' => 'return', 'note' => 'هدف‌ها را بنویسید'])->assertSessionHasNoErrors();
        $this->assertSame(PlanStatus::Returned, $plan->fresh()->status);
        $this->actingAs($this->teacher)->put(route('teaching.items.update', $a), ['topic' => 'الف اصلاح‌شده', 'objectives' => 'هدف', 'week_no' => 1])->assertSessionHasNoErrors();
        $this->assertSame('الف اصلاح‌شده', $a->fresh()->topic);
        $this->actingAs($this->teacher)->get(route('teaching.plans.show', $plan))->assertOk()->assertSee('هدف‌ها را بنویسید');
    }

    public function test_copy_and_access_rules(): void
    {
        $plan = $this->openPlan();
        $this->actingAs($this->teacher)->post(route('teaching.plans.topics', $plan), ['topics' => "یک\nدو"]);

        // پلان همان مضمون در بخش «ب» (مدیر می‌سازد) و کاپی
        $this->otherSection->gradeLevel->subjects()->syncWithoutDetaching([$this->math->id => ['sort_order' => 1]]);
        $this->actingAs($this->principal)->post(route('teaching.plans.open'), ['section' => $this->otherSection->id, 'subject' => $this->math->id])->assertRedirect();
        $other = TeachingPlan::where('section_id', $this->otherSection->id)->firstOrFail();
        $this->actingAs($this->principal)->get(route('teaching.plans.show', $other))->assertOk()->assertSee(__('teaching.copy_from'));
        $this->actingAs($this->principal)->post(route('teaching.plans.copy', $other), ['from' => $plan->id])->assertSessionHasNoErrors();
        $this->assertSame(2, $other->items()->count());
        $this->actingAs($this->principal)->post(route('teaching.plans.copy', $other), ['from' => $plan->id])->assertSessionHasErrors('from');

        // معلم دیگر: نه این پلان را می‌بیند، نه برای مضمونی که از او نیست پلان می‌سازد
        $stranger = $this->userWithRole(Role::Teacher, ['staff_id' => Staff::factory()->create()->id]);
        $this->actingAs($stranger)->get(route('teaching.plans.show', $plan))->assertForbidden();
        $this->actingAs($stranger)->post(route('teaching.plans.open'), ['section' => $this->section->id, 'subject' => $this->dari->id])->assertForbidden();
        $this->actingAs($stranger)->get(route('teaching.plans.index'))->assertOk()->assertSee(__('teaching.no_assignments'));
        $this->actingAs($this->teacher)->get(route('teaching.reports.index'))->assertForbidden();
        $this->actingAs($this->userWithRole(Role::Accountant))->get(route('teaching.plans.index'))->assertForbidden();
    }

    public function test_teacher_report_combines_hours_attendance_marks_and_plans(): void
    {
        $plan = $this->openPlan();
        $this->actingAs($this->teacher)->post(route('teaching.plans.topics', $plan), ['topics' => "یک\nدو"]);
        $this->actingAs($this->teacher)->post(route('teaching.items.progress', $plan->items()->first()), ['status' => 'done']);
        $this->enterMarks($this->mid, $this->math, [30, 20, 10]);   // ۳ از ۶ نمره (دو امتحان × سه شاگرد)
        StaffAttendance::create(['staff_id' => $this->teacher->staff_id, 'date' => '2026-11-15', 'status' => 'present', 'recorded_by' => $this->principal->id]);
        StaffAttendance::create(['staff_id' => $this->teacher->staff_id, 'date' => '2026-11-14', 'status' => 'absent', 'recorded_by' => $this->principal->id]);

        $page = $this->actingAs($this->principal)->get(route('teaching.reports.index'))->assertOk();
        $page->assertSee($this->teacher->staff->full_name)->assertSee('۵۰٪');

        $detail = $this->actingAs($this->principal)->get(route('teaching.reports.show', $this->teacher->staff_id))->assertOk();
        $detail->assertSee($this->math->name)->assertSee('۵۰٪')->assertSee(__('enums.plan_status.draft'));
        $csv = $this->actingAs($this->principal)->get(route('teaching.reports.export'))->streamedContent();
        $this->assertStringContainsString($this->teacher->staff->full_name, $csv);
        $this->actingAs($this->principal)->get(route('teaching.reports.index', ['print' => 1]))->assertOk();
        $this->actingAs($this->teacher)->get(route('teaching.plans.show', [$plan, 'print' => 1]))->assertOk()->assertSee(__('teaching.sign_teacher'));
    }

    public function test_demo_seeder(): void
    {
        $this->seed(DemoTeachingSeeder::class);
        $this->assertSame(1, TeachingPlan::count());
        $this->assertSame(30, TeachingPlanItem::count());
        $this->assertGreaterThan(0, TeachingPlanItem::where('status', 'done')->count());
        $this->actingAs($this->principal)->get(route('teaching.plans.index', ['status' => 'approved']))->assertOk()->assertSee($this->math->name);
    }
}
