<?php

namespace Database\Seeders;

use App\Enums\PlanItemStatus;
use App\Enums\PlanStatus;
use App\Models\AcademicYear;
use App\Models\TeacherAssignment;
use App\Models\TeachingPlan;
use App\Models\TeachingPlanItem;
use App\Models\User;
use Illuminate\Database\Seeder;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use RuntimeException;

/**
 * فقط برای توسعه و آزمایش (مرحله‌ی ۱۶): پلان تدریس نمونه برای بیشتر مضامین سال جاری —
 * ۳۰ موضوع هفته‌وار از آغاز سال؛ موضوع‌های هفته‌های گذشته بیشترشان «تدریس شد»، چند «نیمه» و چند عقب‌مانده.
 * وضعیت‌ها گوناگون (منظور، فرستاده، برگشت، پیش‌نویس) و چند مضمون بدون پلان تا همه‌ی حالت‌ها دیده شود.
 * اگر پلانی وجود داشته باشد، هیچ کاری نمی‌کند.
 */
class DemoTeachingSeeder extends Seeder
{
    private const WEEKS = 30;

    public function run(): void
    {
        if (app()->isProduction()) {
            throw new RuntimeException('DemoTeachingSeeder must not run in production.');
        }
        $year = AcademicYear::current();
        if (! $year || TeachingPlan::exists()) {
            $this->command?->info('Teaching plans already exist (or no current year); demo plans skipped.');

            return;
        }
        $reviewer = User::where('username', 'demo.principal')->value('id') ?? User::where('username', 'admin')->value('id');
        $assignments = TeacherAssignment::with('subject')->whereHas('section', fn ($q) => $q->where('academic_year_id', $year->id))->orderBy('id')->get();

        mt_srand(16);
        DB::transaction(function () use ($year, $assignments, $reviewer) {
            $start = Carbon::parse($year->start_date)->startOfDay();
            $weeksPassed = max(0, (int) floor($start->diffInDays(now()) / 7));
            foreach ($assignments as $i => $a) {
                if ($i % 7 === 6) {
                    continue; // چند مضمون بدون پلان
                }
                $status = match ($i % 7) {
                    0, 1, 2 => PlanStatus::Approved,
                    3 => PlanStatus::Submitted,
                    4 => PlanStatus::Returned,
                    default => PlanStatus::Draft,
                };
                $plan = new TeachingPlan(['notes' => 'کتاب درسی وزارت معارف؛ روش: توضیح، تمرین در صنف، کارخانگی. (نمونه)']);
                $plan->forceFill([
                    'academic_year_id' => $year->id, 'section_id' => $a->section_id, 'subject_id' => $a->subject_id, 'staff_id' => $a->staff_id,
                    'status' => $status, 'submitted_at' => $status === PlanStatus::Draft ? null : $start->copy()->addDays(10),
                    'reviewed_by' => in_array($status, [PlanStatus::Approved, PlanStatus::Returned], true) ? $reviewer : null,
                    'reviewed_at' => in_array($status, [PlanStatus::Approved, PlanStatus::Returned], true) ? $start->copy()->addDays(14) : null,
                    'review_note' => $status === PlanStatus::Returned ? 'فصل سوم را در دو هفته تقسیم کنید و هدف هر درس را بنویسید. (نمونه)' : null,
                ])->save();

                $lag = mt_rand(0, 3); // چند هفته عقب
                for ($w = 1; $w <= self::WEEKS; $w++) {
                    $from = $start->copy()->addWeeks($w - 1);
                    $item = new TeachingPlanItem([
                        'sort_order' => $w, 'week_no' => $w,
                        'planned_from' => $from->toDateString(), 'planned_to' => $from->copy()->addDays(6)->toDateString(),
                        'topic' => $a->subject->name.' — فصل '.(int) ceil($w / 4).'، درس '.$w,
                        'planned_hours' => 3,
                    ]);
                    $item->teaching_plan_id = $plan->id;
                    $done = $w <= $weeksPassed - $lag;
                    $item->status = $done ? PlanItemStatus::Done : ($w === $weeksPassed - $lag + 1 ? PlanItemStatus::Partial : PlanItemStatus::Planned);
                    $item->completed_on = $item->status === PlanItemStatus::Planned ? null : $from->copy()->addDays(5)->toDateString();
                    $item->save();
                }
            }
        });
        $this->command?->info('Demo teaching plans created.');
    }
}
