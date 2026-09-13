<?php

namespace Database\Factories;

use App\Enums\StationType;
use App\Models\Department;
use App\Models\Station;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Str;

class StationFactory extends Factory
{
    protected $model = Station::class;

    public function definition(): array
    {
        return [
            'department_id' => Department::factory(),
            'name' => fake()->word(),
            'code' => strtoupper(Str::random(4)),
            'type' => fake()->randomElement(StationType::cases())->value,
            'queue_prefix' => strtoupper(Str::random(1)),
            'is_active' => true,
        ];
    }
}
