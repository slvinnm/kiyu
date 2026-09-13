<?php

namespace Database\Factories;

use App\Enums\VisitWorkflowStepStatus;
use App\Models\VisitWorkflow;
use App\Models\VisitWorkflowStep;
use App\Models\WorkflowStep;
use Illuminate\Database\Eloquent\Factories\Factory;

class VisitWorkflowStepFactory extends Factory
{
    protected $model = VisitWorkflowStep::class;

    public function definition(): array
    {
        return [
            'visit_workflow_id' => VisitWorkflow::factory(),
            'workflow_step_id' => WorkflowStep::factory(),
            'execution_number' => 1,
            'status' => VisitWorkflowStepStatus::PENDING,
            'started_at' => null,
            'completed_at' => null,
        ];
    }
}
