<?php

namespace Tests;

use App\Enums\Role;
use App\Models\AcademicYear;
use App\Models\GradeLevel;
use App\Models\Section;
use App\Models\User;
use Illuminate\Foundation\Testing\TestCase as BaseTestCase;
use Tests\Concerns\UsesTestDatabase;

abstract class TestCase extends BaseTestCase
{
    use UsesTestDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        // سال جاری در یک متغیر static نگه داشته می‌شود؛ هر تست از نو
        AcademicYear::forgetCurrent();
    }

    protected function userWithRole(Role $role, array $attributes = []): User
    {
        return User::factory()->withRole($role)->create($attributes);
    }

    protected function makeYear(string $name = '1405', string $start = '2026-03-21', string $end = '2026-12-21', bool $current = true): AcademicYear
    {
        $year = AcademicYear::create(['name' => $name, 'start_date' => $start, 'end_date' => $end, 'status' => 'open']);
        if ($current) {
            AcademicYear::where('is_current', true)->update(['is_current' => false]);
            $year->forceFill(['is_current' => true])->save();
        }
        AcademicYear::forgetCurrent();

        return $year;
    }

    /** بخش یک صنف (صنف با ترتیب $gradeOrder از صنف‌های seed‌شده‌ی اول تا دوازدهم) */
    protected function makeSection(AcademicYear $year, int $gradeOrder = 7, string $name = 'الف', array $attributes = []): Section
    {
        return Section::create($attributes + [
            'academic_year_id' => $year->id,
            'grade_level_id' => GradeLevel::where('sort_order', $gradeOrder)->value('id'),
            'name' => $name,
            'shift' => 'morning',
        ])->load('gradeLevel');
    }
}
