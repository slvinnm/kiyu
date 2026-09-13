<?php

namespace Database\Factories;

use App\Enums\VisitWorkflowStatus;
use App\Models\Visit;
use App\Models\VisitWorkflow;
use App\Models\WorkflowVersion;
use Illuminate\Database\Eloquent\Factories\Factory;

class VisitWorkflowFactory extends Factory
{
    protected $model = VisitWorkflow::class;

    public function definition(): array
    {
        return [
            'visit_id' => Visit::factory(),
            'workflow_version_id' => WorkflowVersion::factory(),
            'status' => VisitWorkflowStatus::ACTIVE,
        ];
    }
}
