<?php

namespace Database\Factories;

use App\Models\Patient;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Str;

class PatientFactory extends Factory
{
    protected $model = Patient::class;

    public function definition(): array
    {
        return [
            'user_id' => null,
            'medical_record_number' => 'MRN-' . strtoupper(Str::random(8)),
            'name' => fake()->name(),
            'national_id' => fake()->nik(),
            'date_of_birth' => fake()->date('Y-m-d', '2018-01-01'),
            'gender' => fake()->randomElement(['male', 'female']),
            'phone' => fake()->numerify('08##########'),
            'email' => fake()->unique()->safeEmail(),
            'address' => fake()->address(),
        ];
    }

    public function withUser(): static
    {
        return $this->state(fn(array $attributes) => [
            'user_id' => User::factory(),
        ]);
    }
}
