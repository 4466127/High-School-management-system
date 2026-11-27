<?php

namespace Tests\Feature\Labs;

use App\Enums\ActivityStatus;
use App\Enums\LabEventType;
use App\Enums\Role;
use App\Models\ActivityLog;
use App\Models\Lab;
use App\Models\LabItem;
use App\Models\LabItemEvent;
use App\Models\Period;
use App\Models\PracticalActivity;
use App\Models\Staff;
use App\Models\User;
use App\Services\ImportService;
use Carbon\CarbonImmutable;
use Database\Seeders\DemoLabSeeder;
use Database\Seeders\RolePermissionSeeder;
use Illuminate\Support\Carbon;
use Tests\Feature\Exams\ExamTestCase;

/** مرحله‌ی ۱۸: لابراتوار — لابراتوارها، موجودی و دفتر، هشدارها، فعالیت عملی (پلان، تداخل، حاضری، مصرف، لغو)، ورود از Excel، گزارش */
class LabTest extends ExamTestCase
{
    private User $manager;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(RolePermissionSeeder::class);
        $this->manager = $this->userWithRole(Role::LabManager);
        Carbon::setTestNow('2026-11-20 10:00:00');
        CarbonImmutable::setTestNow('2026-11-20 10:00:00');
    }

    protected function tearDown(): void
    {
        Carbon::setTestNow();
        CarbonImmutable::setTestNow();
        parent::tearDown();
    }

    private function lab(string $name = 'لابراتوار کیمیا'): Lab
    {
        $this->actingAs($this->manager)->post(route('labs.setup.store'), ['name' => $name, 'type' => 'chemistry', 'safety_notes' => 'عینک بپوشید'])->assertSessionHasNoErrors();

        return Lab::where('name', $name)->firstOrFail();
    }

    private function item(Lab $lab, array $overrides = []): LabItem
    {
        $this->actingAs($this->manager)->post(route('labs.items.store'), array_merge([
            'lab_id' => $lab->id, 'name' => 'سلفات مس', 'kind' => 'chemical', 'unit' => 'gram', 'quantity' => '۲۵۰', 'min_quantity' => '100',
            'hazard' => 'toxic', 'source' => 'purchase', 'unit_price' => '3',
        ], $overrides))->assertSessionHasNoErrors()->assertRedirect();

        return LabItem::latest('id')->firstOrFail();
    }

    private function move(LabItem $item, string $type, string $amount, ?string $note = null)
    {
        return $this->actingAs($this->manager)->post(route('labs.items.move', $item), ['type' => $type, 'amount' => $amount, 'note' => $note]);
    }

    public function test_inventory_ledger_and_alerts(): void
    {
        $lab = $this->lab();
        $item = $this->item($lab);
        $this->assertSame('250.00', $item->quantity);
        $this->assertSame(1, $item->events()->count());

        $this->move($item, 'used', '200')->assertSessionHasNoErrors();
        $this->assertSame('50.00', $item->fresh()->quantity);
        $this->assertTrue($item->fresh()->isLow());
        $this->move($item, 'used', '100')->assertSessionHasErrors('amount');
        $this->move($item, 'used', 'abc')->assertSessionHasErrors('amount');
        $this->move($item, 'lost', '5')->assertSessionHasErrors('note');
        // شمارش: ۶۰ شمرده شد ← +۱۰
        $this->move($item, 'adjusted', '60', 'شمارش آخر ماه')->assertSessionHasNoErrors();
        $this->assertSame('60.00', $item->fresh()->quantity);
        $this->assertSame('10.00', LabItemEvent::where('type', 'adjusted')->value('quantity'));
        $this->move($item, 'adjusted', '60', 'دوباره')->assertSessionHasErrors('amount');
        $this->move($item, 'received', '۰٫۵')->assertSessionHasNoErrors();
        $this->assertSame('60.50', $item->fresh()->quantity);

        // وسیله: خراب ← ترمیم ← از بین رفتن
        $scope = $this->item($lab, ['name' => 'میکروسکوپ', 'kind' => 'equipment', 'unit' => 'piece', 'quantity' => '3', 'min_quantity' => '', 'hazard' => '']);
        $this->move($scope, 'repaired', '1')->assertSessionHasErrors('amount');
        $this->move($scope, 'damaged', '2', 'افتاد')->assertSessionHasNoErrors();
        $this->assertSame(['1.00', '2.00'], [$scope->fresh()->quantity, $scope->fresh()->damaged_quantity]);
        $this->move($scope, 'repaired', '1')->assertSessionHasNoErrors();
        $this->move($scope, 'written_off', '1', 'قابل ترمیم نیست')->assertSessionHasNoErrors();
        $this->assertSame(['2.00', '0.00'], [$scope->fresh()->quantity, $scope->fresh()->damaged_quantity]);

        // هشدار تاریخ ختم و فیلترها
        $this->item($lab, ['name' => 'هایدروکساید سودیم', 'expires_on' => '1405/08/01']);
        $this->actingAs($this->manager)->get(route('labs.items.index', ['alert' => 'expired']))->assertOk()->assertSee('هایدروکساید سودیم')->assertDontSee('سلفات مس');
        $this->actingAs($this->manager)->get(route('labs.items.index', ['alert' => 'low']))->assertOk()->assertSee('سلفات مس');
        $this->actingAs($this->manager)->get(route('labs.items.show', $scope))->assertOk()->assertSee('قابل ترمیم نیست');
        $this->actingAs($this->manager)->get(route('labs.items.index', ['print' => 1, 'lab' => $lab->id]))->assertOk()->assertSee(__('labs.counted'));
        $this->assertStringContainsString('سلفات مس', $this->actingAs($this->manager)->get(route('labs.items.export'))->streamedContent());

        // ویرایش؛ کود یکتا
        $this->actingAs($this->manager)->put(route('labs.items.update', $item), ['lab_id' => $lab->id, 'name' => 'سلفات مس (آبی)', 'kind' => 'chemical', 'unit' => 'gram', 'source' => 'purchase', 'code' => 'K-1', 'is_active' => 1])->assertSessionHasNoErrors();
        $this->actingAs($this->manager)->put(route('labs.items.update', $scope), ['lab_id' => $lab->id, 'name' => 'میکروسکوپ', 'kind' => 'equipment', 'unit' => 'piece', 'source' => 'purchase', 'code' => 'K-1'])->assertSessionHasErrors('code');
        $this->assertSame('60.50', $item->fresh()->quantity);   // ویرایش مقدار را عوض نمی‌کند

        // دسترسی: معلم می‌بیند، تغییر نمی‌دهد؛ حسابدار نمی‌بیند
        $this->actingAs($this->teacher)->get(route('labs.items.index'))->assertOk()->assertDontSee(__('labs.add_item'));
        $this->actingAs($this->teacher)->post(route('labs.items.move', $item), ['type' => 'used', 'amount' => '1'])->assertForbidden();
        $this->actingAs($this->teacher)->get(route('labs.setup.index'))->assertForbidden();
        $this->actingAs($this->userWithRole(Role::Accountant))->get(route('labs.items.index'))->assertForbidden();
        $this->assertTrue(ActivityLog::where('action', 'lab.stock_used')->exists());
    }

    public function test_practical_activity_flow(): void
    {
        $lab = $this->lab();
        $item = $this->item($lab, ['name' => 'کاغذ لتمس', 'kind' => 'consumable', 'unit' => 'box', 'quantity' => '3', 'min_quantity' => '', 'hazard' => '']);
        $period = Period::create(['name' => 'ساعت اول', 'shift' => 'morning', 'start_time' => '08:00', 'end_time' => '08:40', 'sort_order' => 1]);
        $data = ['lab_id' => $lab->id, 'activity_date' => '1405/09/01', 'period_id' => $period->id, 'title' => 'تیزاب و القلی', 'max_score' => 10];

        // معلم فقط مضمون خودش (ریاضی)، نه دری
        $this->actingAs($this->teacher)->get(route('labs.activities.create'))->assertOk()->assertSee($this->math->name)->assertDontSee($this->dari->name.' — ');
        $this->actingAs($this->teacher)->post(route('labs.activities.store'), $data + ['assignment' => $this->section->id.'|'.$this->dari->id])->assertForbidden();
        $this->actingAs($this->teacher)->post(route('labs.activities.store'), $data + ['assignment' => $this->section->id.'|'.$this->math->id])->assertSessionHasNoErrors();
        $activity = PracticalActivity::firstOrFail();
        $this->assertSame($this->teacher->staff_id, $activity->staff_id);
        $this->assertSame('2026-11-22', $activity->activity_date->toDateString());

        // تداخل: همان لابراتوار، روز و ساعت
        $this->actingAs($this->manager)->post(route('labs.activities.store'), $data + ['assignment' => $this->section->id.'|'.$this->dari->id])->assertSessionHasErrors('period_id');
        // معلم دیگر نمی‌تواند ثبت کند
        $stranger = $this->userWithRole(Role::Teacher, ['staff_id' => Staff::factory()->create()->id]);
        $this->actingAs($stranger)->get(route('labs.activities.show', $activity))->assertOk();
        $this->actingAs($stranger)->post(route('labs.activities.result', $activity), [])->assertForbidden();

        // ثبت حاضری و نمره
        [$e1, $e2, $e3] = array_map(fn ($e) => $e->id, $this->students);
        $this->actingAs($this->teacher)->post(route('labs.activities.result', $activity), ['rows' => [$e1 => ['present' => 1, 'score' => '11']]])->assertSessionHasErrors('rows');
        $this->actingAs($this->teacher)->post(route('labs.activities.result', $activity), [
            'rows' => [$e1 => ['present' => 1, 'score' => '9.5'], $e2 => ['present' => 0, 'score' => '7'], $e3 => ['present' => 1]],
            'result_note' => 'همه درست تشخیص دادند',
        ])->assertSessionHasNoErrors()->assertRedirect();
        $activity->refresh();
        $this->assertSame(ActivityStatus::Done, $activity->status);
        $this->assertSame(2, $activity->students()->where('present', true)->count());
        $this->assertNull($activity->students()->where('enrollment_id', $e2)->value('score'));   // غیرحاضر نمره ندارد
        $this->assertSame('9.50', $activity->students()->where('enrollment_id', $e1)->value('score'));

        // مصرف مواد ← از موجودی کم و به فعالیت وصل
        $this->actingAs($this->teacher)->post(route('labs.activities.material', $activity), ['item_id' => $item->id, 'amount' => '1'])->assertSessionHasNoErrors();
        $this->assertSame('2.00', $item->fresh()->quantity);
        $this->assertSame($activity->id, LabItemEvent::where('type', LabEventType::Used->value)->value('practical_activity_id'));
        $other = $this->item($this->lab('لابراتوار فزیک'), ['name' => 'بتری']);
        $this->actingAs($this->teacher)->post(route('labs.activities.material', $activity), ['item_id' => $other->id, 'amount' => '1'])->assertSessionHasErrors('item');

        // انجام‌شده لغو یا ویرایش نمی‌شود؛ پلان‌شده لغو با دلیل
        $this->actingAs($this->teacher)->post(route('labs.activities.cancel', $activity), ['reason' => 'x'])->assertSessionHasErrors('activity');
        $this->actingAs($this->teacher)->post(route('labs.activities.store'), array_merge($data, ['assignment' => $this->section->id.'|'.$this->math->id, 'activity_date' => '1405/09/05', 'title' => 'آینده']))->assertSessionHasNoErrors();
        $future = PracticalActivity::where('title', 'آینده')->firstOrFail();
        $this->actingAs($this->teacher)->put(route('labs.activities.update', $future), array_merge($data, ['activity_date' => '1405/09/06', 'title' => 'آینده']))->assertSessionHasNoErrors();
        $this->assertSame('2026-11-27', $future->fresh()->activity_date->toDateString());
        $this->actingAs($this->teacher)->get(route('dashboard'))->assertOk()->assertSee(__('labs.dashboard_mine'));
        $this->actingAs($this->teacher)->post(route('labs.activities.cancel', $future), [])->assertSessionHasErrors('reason');
        $this->actingAs($this->teacher)->post(route('labs.activities.cancel', $future), ['reason' => 'رخصتی'])->assertSessionHasNoErrors();
        $this->assertSame(ActivityStatus::Cancelled, $future->fresh()->status);

        // فهرست، جدول هفته، چاپ ورق
        $this->actingAs($this->teacher)->get(route('labs.activities.index', ['status' => 'done']))->assertOk()->assertSee('تیزاب و القلی');
        $this->actingAs($this->teacher)->get(route('labs.schedule.index', ['week' => '2026-11-22']))->assertOk()->assertSee('تیزاب و القلی');
        $this->actingAs($this->teacher)->get(route('labs.activities.show', [$activity, 'print' => 1]))->assertOk()->assertSee('عینک بپوشید')->assertSee(__('labs.worksheet'));
        $this->actingAs($this->userWithRole(Role::Accountant))->get(route('labs.activities.index'))->assertForbidden();
    }

    public function test_import_lab_items_from_csv(): void
    {
        $this->lab();
        $path = tempnam(sys_get_temp_dir(), 'lab').'.csv';
        file_put_contents($path, "\xEF\xBB\xBF".implode("\n", [
            'لابراتوار *,نام *,نوع,واحد,مقدار موجود,حد کمبود,خطر,منبع',
            'لابراتوار کیمیا,بیکر,ظروف شیشه‌ای,عدد,12,4,,',
            'لابراتوار کیمیا,الکول,مواد کیمیاوی,ملی‌لیتر,1500.5,,قابل اشتعال,اهدایی',
            'لابراتوار ناموجود,ترازو,,,1,,,',
            'لابراتوار کیمیا,بیکر,,,3,,,',
        ]));
        $service = app(ImportService::class);
        $result = $service->preview('lab_items', $path, 'csv');
        $this->assertSame(['ok', 'ok', 'error', 'duplicate'], array_column($result['rows'], 'status'));
        $this->assertSame(2, $service->import('lab_items', $result['rows'], $this->manager)['imported']);
        $alcohol = LabItem::where('name', 'الکول')->firstOrFail();
        $this->assertSame('1500.50', $alcohol->quantity);
        $this->assertSame('flammable', $alcohol->hazard->value);
        $this->assertSame(1, $alcohol->events()->count());
        @unlink($path);
        $this->actingAs($this->manager)->get(route('import.template', 'lab_items'))->assertOk();
    }

    public function test_reports_setup_and_demo_seeder(): void
    {
        $this->seed(DemoLabSeeder::class);
        $this->assertSame(4, Lab::count());
        $this->assertGreaterThan(20, LabItem::count());
        $this->assertGreaterThan(0, LabItem::query()->alert('expired')->count());
        $this->assertGreaterThan(0, PracticalActivity::where('status', 'done')->count());
        $this->seed(DemoLabSeeder::class);   // دوباره امن
        $this->assertSame(4, Lab::count());

        $this->actingAs($this->manager)->get(route('labs.reports.index'))->assertOk()->assertSee('هایدروکساید سودیم');
        $this->actingAs($this->manager)->get(route('labs.reports.index', ['print' => 1]))->assertOk();
        $this->actingAs($this->manager)->get(route('labs.setup.index'))->assertOk()->assertSee('لابراتوار کیمیا');
        $lab = Lab::firstOrFail();
        $this->actingAs($this->manager)->put(route('labs.setup.update', $lab), ['name' => $lab->name, 'type' => $lab->type->value])->assertSessionHasNoErrors();
        $this->assertFalse($lab->fresh()->is_active);
        $this->actingAs($this->manager)->post(route('labs.setup.store'), ['name' => $lab->name, 'type' => 'physics'])->assertSessionHasErrors('name');
        $this->actingAs($this->manager)->get(route('dashboard'))->assertOk()->assertSee(__('labs.dashboard_title'));
        $this->actingAs($this->manager)->get(route('labs.activities.index', ['status' => 'all']))->assertOk();
        $this->actingAs($this->manager)->get(route('labs.schedule.index'))->assertOk();
    }
}
