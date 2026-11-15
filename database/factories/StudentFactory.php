<?php

namespace Database\Factories;

use App\Enums\Gender;
use App\Enums\StudentStatus;
use App\Models\Student;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * داده‌ی ساختگی (غیرواقعی) برای تست و محیط توسعه.
 *
 * @extends Factory<Student>
 */
class StudentFactory extends Factory
{
    public const BOY_NAMES = ['احمد', 'محمود', 'فرهاد', 'نوید', 'سمیع', 'جاوید', 'بصیر', 'نجیب', 'رحیم', 'شکیب', 'ولی', 'عارف', 'هارون', 'یما', 'میلاد'];

    public const GIRL_NAMES = ['مریم', 'فرشته', 'زهرا', 'نرگس', 'ملالی', 'شبنم', 'سحر', 'لیلا', 'پروانه', 'مژگان', 'زرغونه', 'هدیه', 'نیلوفر', 'ستاره', 'آرزو'];

    public const MALE_ADULT_NAMES = ['عبدالله', 'غلام', 'محمد', 'نورالله', 'عبدالرحمن', 'حبیب', 'سید', 'عزیز', 'رسول', 'کریم', 'شریف', 'قادر'];

    public const LAST_NAMES = ['احمدی', 'رحیمی', 'کریمی', 'نوری', 'صدیقی', 'حکیمی', 'امیری', 'یوسفی', 'سلطانی', 'نظری', 'وفا', 'هاشمی'];

    public function definition(): array
    {
        $gender = fake()->randomElement(Gender::cases());

        return [
            'admission_no' => (string) fake()->unique()->numberBetween(10000, 99999),
            'first_name' => fake()->randomElement($gender === Gender::Male ? self::BOY_NAMES : self::GIRL_NAMES),
            'last_name' => fake()->randomElement(self::LAST_NAMES),
            'father_name' => fake()->randomElement(self::MALE_ADULT_NAMES),
            'grandfather_name' => fake()->randomElement(self::MALE_ADULT_NAMES),
            'gender' => $gender,
            'birth_date' => fake()->dateTimeBetween('-18 years', '-6 years')->format('Y-m-d'),
            'phone' => '07'.fake()->numerify('########'),
            'province' => fake()->randomElement(config('afghanistan.provinces')),
            'district' => 'ناحیه '.fake()->numberBetween(1, 22),
            'village' => null,
            'admission_date' => fake()->dateTimeBetween('-5 years')->format('Y-m-d'),
            'status' => StudentStatus::Active,
        ];
    }

    public function inactive(): static
    {
        return $this->state(fn () => ['status' => StudentStatus::Inactive, 'status_reason' => 'آزمایشی']);
    }
}
