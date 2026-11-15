<?php

namespace Database\Factories;

use App\Enums\EducationLevel;
use App\Enums\EmploymentStatus;
use App\Enums\Gender;
use App\Enums\StaffType;
use App\Models\Staff;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Staff>
 */
class StaffFactory extends Factory
{
    public function definition(): array
    {
        $gender = fake()->randomElement(Gender::cases());

        return [
            'staff_type' => StaffType::Teacher,
            'first_name' => fake()->randomElement($gender === Gender::Male ? StudentFactory::MALE_ADULT_NAMES : StudentFactory::GIRL_NAMES),
            'last_name' => fake()->randomElement(StudentFactory::LAST_NAMES),
            'father_name' => fake()->randomElement(StudentFactory::MALE_ADULT_NAMES),
            'gender' => $gender,
            'phone' => '07'.fake()->numerify('########'),
            'education' => fake()->randomElement([EducationLevel::Grade14, EducationLevel::Bachelor, EducationLevel::Bachelor, EducationLevel::Master]),
            'specialization' => fake()->randomElement(['ریاضی', 'دری', 'پشتو', 'انگلیسی', 'کیمیا', 'فزیک', 'بیولوژی', 'تاریخ']),
            'hire_date' => fake()->dateTimeBetween('-15 years', '-1 month')->format('Y-m-d'),
            'position' => 'معلم',
            'employment_status' => EmploymentStatus::Active,
        ];
    }

    public function ofType(StaffType $type): static
    {
        return $this->state(fn () => ['staff_type' => $type]);
    }
}
