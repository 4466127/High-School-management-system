<?php

namespace Database\Seeders;

use App\Enums\StaffType;
use App\Models\Guardian;
use App\Models\Staff;
use App\Models\Student;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;
use RuntimeException;

/**
 * فقط برای توسعه: دانش‌آموزان و کارکنان ساختگی (نام‌ها غیرواقعی).
 * اگر دانش‌آموزی وجود داشته باشد، هیچ کاری نمی‌کند (تا داده‌ی واقعی یا قبلی دست نخورد).
 */
class DemoSchoolSeeder extends Seeder
{
    public function run(): void
    {
        if (app()->isProduction()) {
            throw new RuntimeException('DemoSchoolSeeder must not run in production.');
        }

        if (Student::withTrashed()->exists() || Staff::withTrashed()->exists()) {
            $this->command?->info('Students or staff already exist; demo school data skipped.');

            return;
        }

        DB::transaction(function () {
            Staff::factory()->count(12)->create();
            Staff::factory()->ofType(StaffType::Admin)->create(['position' => 'سرمعلم']);
            Staff::factory()->ofType(StaffType::Service)->create(['position' => 'محافظ']);
            Staff::factory()->ofType(StaffType::CourseTeacher)->create(['position' => 'معلم کورس انگلیسی', 'specialization' => 'انگلیسی']);

            $number = 1000;
            Guardian::factory()->count(30)->create()->each(function (Guardian $guardian) use (&$number) {
                // هر سرپرست ۱ تا ۳ فرزند (خواهر و برادر با یک سرپرست)
                Student::factory()
                    ->count(random_int(1, 3))
                    ->sequence(function () use (&$number) {
                        return ['admission_no' => (string) ++$number];
                    })
                    ->create(['father_name' => explode(' ', $guardian->name)[0]])
                    ->each(fn (Student $student) => $student->guardians()->attach($guardian->id, ['relation' => 'پدر', 'is_primary' => true]));
            });
        });

        $this->command?->info('Demo school data: '.Student::count().' students, '.Staff::count().' staff.');
    }
}
