<?php

namespace Database\Factories;

use App\Models\Workflow;
use App\Models\WorkflowVersion;
use Illuminate\Database\Eloquent\Factories\Factory;

class WorkflowVersionFactory extends Factory
{
    protected $model = WorkflowVersion::class;

    public function definition(): array
    {
        return [
            'workflow_id' => Workflow::factory(),
            'version_number' => 1,
            'is_active' => true,
            'published_at' => now(),
        ];
    }
}
