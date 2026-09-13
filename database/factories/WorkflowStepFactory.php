<?php

namespace Database\Factories;

use App\Models\Station;
use App\Models\WorkflowStep;
use App\Models\WorkflowVersion;
use Illuminate\Database\Eloquent\Factories\Factory;

class WorkflowStepFactory extends Factory
{
    protected $model = WorkflowStep::class;

    public function definition(): array
    {
        return [
            'workflow_version_id' => WorkflowVersion::factory(),
            'station_id' => Station::factory(),
            'name' => fake()->words(2, true),
            'sequence' => $this->faker->numberBetween(1, 10),
            'requires_queue' => true,
            'is_optional' => false,
            'is_repeatable' => false,
            'can_skip' => false,
            'completion_requirements' => [],
            'entry_conditions' => [],
        ];
    }
}
