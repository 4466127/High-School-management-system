<?php

namespace Database\Factories;

use App\Models\Guardian;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Guardian>
 */
class GuardianFactory extends Factory
{
    public function definition(): array
    {
        return [
            'name' => fake()->randomElement(StudentFactory::MALE_ADULT_NAMES).' '.fake()->randomElement(StudentFactory::LAST_NAMES),
            'phone' => '07'.fake()->numerify('########'),
            'occupation' => fake()->randomElement(['دهقان', 'دکاندار', 'معلم', 'کارمند', 'راننده', null]),
        ];
    }
}
