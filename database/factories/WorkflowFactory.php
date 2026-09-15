<?php

namespace Database\Factories;

use App\Models\Department;
use App\Models\Workflow;
use Illuminate\Database\Eloquent\Factories\Factory;

class WorkflowFactory extends Factory
{
    protected $model = Workflow::class;

    public function definition(): array
    {
        return [
            'department_id' => Department::factory(),
            'name' => fake()->words(3, true) . ' Workflow',
            'is_active' => true,
        ];
    }
}
