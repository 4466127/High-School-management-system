<?php

namespace Database\Seeders;

use App\Enums\EnrollmentStatus;
use App\Enums\LabEventType;
use App\Enums\RoomType;
use App\Models\AcademicYear;
use App\Models\Enrollment;
use App\Models\Lab;
use App\Models\LabItem;
use App\Models\Period;
use App\Models\Room;
use App\Models\Staff;
use App\Models\TeacherAssignment;
use App\Models\User;
use App\Services\AttendanceService;
use App\Services\LabService;
use App\Support\Quantity;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;
use RuntimeException;

/**
 * فقط برای توسعه و آزمایش (مرحله‌ی ۱۸): لابراتوارهای نمونه (کیمیا، فزیک، بیولوژی، کمپیوتر) با موجودی،
 * چند هشدار (کمبود، تاریخ‌گذشته، نزدیک ختم، خراب)، دفتر ورود و خروج، و فعالیت‌های عملی گذشته (با حاضری و مصرف مواد) و پیش رو.
 * اگر لابراتواری وجود داشته باشد، هیچ کاری نمی‌کند.
 */
class DemoLabSeeder extends Seeder
{
    /** [نام، نوع، واحد، مقدار، حد کمبود، خطر، روز تا ختم (null = ندارد؛ منفی = گذشته)، قیمت، مشخصات] */
    private const ITEMS = [
        'chemistry' => [
            ['بیکر ۲۵۰ ملی‌لیتر', 'glassware', 'piece', 24, 6, null, null, 120, 'شیشه‌ی مقاوم به حرارت'],
            ['تست‌تیوب', 'glassware', 'piece', 60, 20, null, null, 25, ''],
            ['فلاسک مخروطی ۵۰۰ ملی‌لیتر', 'glassware', 'piece', 10, 3, null, null, 250, ''],
            ['چراغ الکلی', 'equipment', 'piece', 8, 2, null, null, 400, ''],
            ['ترازوی دیجیتال', 'equipment', 'piece', 2, 1, null, null, 6500, 'دقت ۰٫۰۱ گرام'],
            ['عینک ایمنی', 'equipment', 'piece', 30, 10, null, null, 150, ''],
            ['اسید هایدروکلوریک', 'chemical', 'ml', 900, 250, 'corrosive', 400, 2, 'HCl، ۱ مولار'],
            ['سلفات مس', 'chemical', 'gram', 120, 200, 'toxic', 300, 3, 'CuSO₄'],
            ['هایدروکساید سودیم', 'chemical', 'gram', 400, 100, 'corrosive', -20, 2, 'NaOH'],
            ['الکول ایتایل', 'chemical', 'ml', 1500, 500, 'flammable', 45, 1, 'C₂H₅OH، ۹۶٪'],
            ['کاغذ لتمس', 'consumable', 'box', 6, 2, null, 200, 180, 'سرخ و آبی'],
        ],
        'physics' => [
            ['ملتی‌متر دیجیتال', 'electronic', 'piece', 6, 2, null, null, 1200, ''],
            ['بتری ۹ ولت', 'consumable', 'piece', 20, 10, null, null, 60, ''],
            ['ست مقناطیس', 'equipment', 'set', 5, 1, null, null, 800, ''],
            ['عدسیه‌ی محدب', 'glassware', 'piece', 12, 4, null, null, 200, ''],
            ['زمان‌سنج (ستاپ‌واچ)', 'equipment', 'piece', 6, 2, null, null, 450, ''],
            ['فنر و وزنه‌ها', 'equipment', 'set', 4, 1, null, null, 900, 'قانون هوک'],
        ],
        'biology' => [
            ['میکروسکوپ نوری', 'equipment', 'piece', 6, 2, null, null, 9000, 'بزرگ‌نمایی ۴۰ تا ۴۰۰ برابر'],
            ['سلاید شیشه‌ای', 'glassware', 'box', 4, 2, null, null, 300, '۵۰ عدد در بسته'],
            ['مودل اسکلیت انسان', 'specimen', 'piece', 1, null, null, null, 15000, ''],
            ['مودل قلب', 'specimen', 'piece', 2, null, null, null, 3500, ''],
            ['محلول ید', 'chemical', 'ml', 200, 100, 'irritant', 30, 4, 'برای رنگ کردن نشایسته'],
            ['دستکش یک‌بار مصرف', 'consumable', 'box', 3, 2, null, null, 350, '۱۰۰ عدد'],
        ],
        'computer' => [
            ['کمپیوتر رومیزی', 'electronic', 'piece', 15, 10, null, null, 28000, ''],
            ['پروجکتور', 'electronic', 'piece', 1, null, null, null, 32000, ''],
            ['سویچ شبکه ۲۴ پورت', 'electronic', 'piece', 1, null, null, null, 7000, ''],
        ],
    ];

    private const LABS = [
        'chemistry' => ['لابراتوار کیمیا', "بی اجازه‌ی معلم به هیچ ماده دست نزنید.\nعینک ایمنی و دستکش بپوشید.\nمواد کیمیاوی را بو نکنید و نچشید.\nخوردن و نوشیدن در لابراتوار منع است.\nبعد از کار دست‌ها را بشویید."],
        'physics' => ['لابراتوار فزیک', "وسایل برقی را بی اجازه وصل نکنید.\nبا دست تر به سیم و بتری دست نزنید."],
        'biology' => ['لابراتوار بیولوژی', "میکروسکوپ را با دو دست حمل کنید.\nسلایدهای شکسته را به معلم بگویید."],
        'computer' => ['صنف کمپیوتر', "خوردن و نوشیدن کنار کمپیوترها منع است.\nکمپیوتر را درست خاموش کنید."],
    ];

    /** [عنوان، مواد مصرفی (نام => مقدار)] برای هر نوع لابراتوار */
    private const ACTIVITIES = [
        'chemistry' => [['شناخت تیزاب و القلی با کاغذ لتمس', ['کاغذ لتمس' => 1]], ['واکنش سلفات مس با آب', ['سلفات مس' => 30]], ['اندازه‌گیری کتله با ترازو', []]],
        'physics' => [['اندازه‌گیری ولتاژ بتری', ['بتری ۹ ولت' => 2]], ['قانون هوک با فنر', []], ['تشکیل تصویر در عدسیه', []]],
        'biology' => [['دیدن حجره‌ی پیاز با میکروسکوپ', ['محلول ید' => 20, 'دستکش یک‌بار مصرف' => 1]], ['شناخت اعضای بدن با مودل', []]],
        'computer' => [['آشنایی با Word', []], ['ساختن جدول در Excel', []]],
    ];

    public function run(): void
    {
        if (app()->isProduction()) {
            throw new RuntimeException('DemoLabSeeder must not run in production.');
        }
        if (Lab::exists()) {
            $this->command?->info('Labs already exist; demo labs skipped.');

            return;
        }
        $user = User::where('username', 'demo.lab-manager')->first() ?? User::where('username', 'admin')->first() ?? User::first();
        if (! $user) {
            return;
        }
        $service = app(LabService::class);
        $year = AcademicYear::current();
        $today = AttendanceService::today();
        $start = $year ? $year->start_date->toDateString() : $today->subMonths(6)->toDateString();
        $rooms = Room::where('type', RoomType::Lab->value)->orderBy('id')->pluck('id')->all();
        $staff = Staff::where('employment_status', 'active')->orderBy('id')->pluck('id')->all();
        mt_srand(18);

        DB::transaction(function () use ($service, $user, $year, $today, $start, $rooms, $staff) {
            $labs = [];
            $i = 0;
            foreach (self::LABS as $type => [$name, $safety]) {
                $labs[$type] = $service->saveLab(null, ['name' => $name, 'type' => $type, 'room_id' => $rooms[$i] ?? null, 'staff_id' => $staff[$i + 1] ?? null, 'safety_notes' => $safety, 'is_active' => true], $user);
                $i++;
                foreach (self::ITEMS[$type] as [$itemName, $kind, $unit, $qty, $min, $hazard, $expiry, $price, $spec]) {
                    $item = $service->createItem([
                        'lab_id' => $labs[$type]->id, 'name' => $itemName, 'kind' => $kind, 'unit' => $unit, 'min_quantity' => $min !== null ? (string) $min : null,
                        'hazard' => $hazard, 'expires_on' => $expiry !== null ? $today->addDays($expiry)->toDateString() : null, 'unit_price' => (string) $price,
                        'source' => $kind === 'electronic' ? 'donation' : 'purchase', 'specification' => $spec ?: null, 'location' => 'الماری '.mt_rand(1, 4), 'is_active' => true,
                    ], 0, $user);
                    $service->move($item, LabEventType::Received, $qty * 100, $start, __('labs.initial_stock'), $user);
                }
            }
            // یک میکروسکوپ و یک ملتی‌متر خراب
            foreach (['میکروسکوپ نوری', 'ملتی‌متر دیجیتال'] as $broken) {
                $service->move(LabItem::where('name', $broken)->firstOrFail(), LabEventType::Damaged, 100, $today->subDays(10)->toDateString(), 'نمونه: هنگام کار افتاد', $user);
            }

            if (! $year) {
                return;
            }
            // فعالیت‌ها: برای چند معلم مضمون؛ گذشته (انجام‌شده) و پیش رو (پلان)
            $assignments = TeacherAssignment::with(['section', 'subject'])->whereHas('section', fn ($q) => $q->where('academic_year_id', $year->id))->orderBy('id')->get();
            $teacherStaff = User::where('username', 'demo.teacher')->value('staff_id');
            $assignments = $assignments->sortByDesc(fn ($a) => (int) ($a->staff_id === $teacherStaff))->values()->take(10);
            $periods = Period::where('is_active', true)->where('is_break', false)->orderBy('sort_order')->pluck('id')->all();
            $types = array_keys(self::ACTIVITIES);
            foreach ($assignments as $k => $a) {
                $type = $types[$k % count($types)];
                foreach (self::ACTIVITIES[$type] as $j => [$title, $materials]) {
                    $past = $j < count(self::ACTIVITIES[$type]) - 1;
                    $date = $past ? $today->subDays(7 * ($j + 1) + $k) : $today->addDays(1 + $k % 6);
                    if (strtolower($date->format('l')) === 'friday') {
                        $date = $date->addDay();
                    }
                    try {
                        $activity = $service->planActivity($a->section, $a->subject, [
                            'lab_id' => $labs[$type]->id, 'activity_date' => $date->toDateString(), 'period_id' => $periods[($k + $j) % max(1, count($periods))] ?? null,
                            'title' => $title, 'objectives' => 'نمونه: آشنایی عملی شاگردان با موضوع درس', 'materials_note' => implode('، ', array_keys($materials)) ?: null,
                            'max_score' => $j === 0 ? 10 : null,
                        ], $user);
                    } catch (ValidationException) {
                        continue;   // مضمون از صنف نیست یا ساعت گرفته است
                    }
                    if (! $past) {
                        continue;
                    }
                    $rows = Enrollment::where('section_id', $a->section_id)->where('status', EnrollmentStatus::Active)->pluck('id')
                        ->mapWithKeys(fn ($id) => [$id => ['present' => mt_rand(1, 10) > 1, 'score' => $j === 0 ? (string) mt_rand(5, 10) : null]])->all();
                    $service->recordResult($activity, $rows, 'نمونه: بیشتر گروه‌ها تجربه را درست انجام دادند.', $user);
                    foreach ($materials as $itemName => $amount) {
                        $item = LabItem::where('lab_id', $labs[$type]->id)->where('name', $itemName)->first();
                        if ($item && Quantity::toHundredths($item->fresh()->quantity) >= $amount * 100) {
                            $service->useMaterial($activity, $item, $amount * 100, $user);
                        }
                    }
                }
            }
        });
        $this->command?->info('Demo labs created.');
    }
}
