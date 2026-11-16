<?php

namespace Tests\Feature\Finance;

use App\Enums\Role;
use App\Models\AcademicYear;
use App\Models\Enrollment;
use App\Models\FeeRate;
use App\Models\FeeType;
use App\Models\Section;
use App\Models\Setting;
use App\Models\Student;
use App\Models\User;
use App\Services\EnrollmentService;
use Carbon\Carbon;
use Carbon\CarbonImmutable;
use Illuminate\Testing\TestResponse;
use Tests\TestCase;

/** زمینه‌ی تست‌های مالی: فیس و دفتر روشن، صنف هفتم با نرخ ۱۰۰۰، صنف هشتم بدون نرخ، سه دانش‌آموز. */
abstract class FinanceTestCase extends TestCase
{
    protected AcademicYear $year;

    protected Section $section;

    protected Section $noRateSection;

    protected FeeType $monthly;

    protected User $accountant;

    /** @var list<Enrollment> */
    protected array $students = [];

    protected function setUp(): void
    {
        parent::setUp();
        Carbon::setTestNow('2026-11-16 09:00:00');
        CarbonImmutable::setTestNow('2026-11-16 09:00:00');
        Setting::put('fees_enabled', '1');
        Setting::put('school_ledger_enabled', '1');

        $this->year = $this->makeYear();
        $this->section = $this->makeSection($this->year, 7);
        $this->noRateSection = $this->makeSection($this->year, 8);
        $this->monthly = FeeType::create(['name' => 'فیس ماهوار تست', 'period' => 'monthly', 'sort_order' => 1]);
        FeeRate::create(['fee_type_id' => $this->monthly->id, 'academic_year_id' => $this->year->id, 'grade_level_id' => $this->section->grade_level_id, 'amount' => 1000]);
        $this->accountant = $this->userWithRole(Role::Accountant);

        $service = app(EnrollmentService::class);
        foreach (['اول', 'دوم', 'سوم'] as $name) {
            $this->students[] = $service->enroll(Student::factory()->create(['first_name' => 'شاگرد'.$name]), $this->section, '2026-03-21');
        }
        $service->enroll(Student::factory()->create(['first_name' => 'بی‌نرخ']), $this->noRateSection, '2026-03-21');
    }

    protected function issue(string $period, array $sections = []): TestResponse
    {
        return $this->actingAs($this->accountant)->post(route('finance.invoices.store'), [
            'fee_type_id' => $this->monthly->id,
            'period_label' => $period,
            'section_ids' => $sections ?: [$this->section->id, $this->noRateSection->id],
        ]);
    }

    protected function pay(Student $student, string $amount, array $allocations = []): TestResponse
    {
        return $this->actingAs($this->accountant)->post(route('finance.payments.store', $student), [
            'amount' => $amount, 'paid_on' => '1405/08/25', 'allocations' => $allocations,
        ]);
    }
}
