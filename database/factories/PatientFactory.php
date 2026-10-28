<?php

namespace Database\Factories;

use App\Models\Patient;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Patient>
 */
class PatientFactory extends Factory
{
    protected $model = Patient::class;

    public function definition(): array
    {
        return [
            'user_id' => null,
            'mrn' => 'MRN-' . fake()->unique()->numerify('########'),
            'nik' => fake()->unique()->numerify('################'),
            'name' => fake()->name(),
            'date_of_birth' => fake()->date('Y-m-d', '-30 years'),
            'gender' => fake()->randomElement(['male', 'female']),
            'phone' => fake()->numerify('08##########'),
            'email' => fake()->unique()->safeEmail(),
            'address' => fake()->address(),
        ];
    }
}
