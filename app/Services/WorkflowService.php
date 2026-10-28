<?php

namespace App\Services;

use App\Enums\StationType;
use App\Exceptions\InvalidWorkflowException;
use App\Models\Department;
use App\Models\Workflow;
use App\Models\WorkflowStep;

class WorkflowService
{
    /**
     * Resolve the active primary workflow for a department.
     *
     * @throws InvalidWorkflowException
     */
    public function resolvePrimaryWorkflow(int $departmentId): Workflow
    {
        $department = Department::query()->find($departmentId);
        if (! $department || ! $department->is_active) {
            throw new InvalidWorkflowException("Active department #{$departmentId} not found.");
        }

        /** @var Workflow|null $workflow */
        $workflow = Workflow::query()
            ->where('department_id', $departmentId)
            ->where('is_active', true)
            ->latest('id')
            ->first();

        if (! $workflow) {
            throw new InvalidWorkflowException("No active workflow configured for department #{$departmentId}.");
        }

        return $workflow;
    }

    /**
     * Resolve the active registration step for a workflow.
     *
     * @throws InvalidWorkflowException
     */
    public function resolveRegistrationStep(Workflow $workflow): WorkflowStep
    {
        $registrationSteps = WorkflowStep::query()
            ->with('station')
            ->where('workflow_id', $workflow->id)
            ->where('is_active', true)
            ->whereHas('station', function ($query) {
                $query->where('type', StationType::Registration->value)
                    ->where('is_active', true);
            })
            ->get();

        if ($registrationSteps->isEmpty()) {
            throw new InvalidWorkflowException("Workflow #{$workflow->id} has no active registration step.");
        }

        if ($registrationSteps->count() > 1) {
            throw new InvalidWorkflowException("Workflow #{$workflow->id} has multiple active registration steps.");
        }

        return $registrationSteps->first();
    }

    /**
     * Resolve the next active workflow step after the given sequence.
     */
    public function resolveNextStep(Workflow $workflow, int $currentSequence): ?WorkflowStep
    {
        return WorkflowStep::query()
            ->with('station')
            ->where('workflow_id', $workflow->id)
            ->where('is_active', true)
            ->where('sequence', '>', $currentSequence)
            ->whereHas('station', function ($query) {
                $query->where('is_active', true);
            })
            ->orderBy('sequence', 'asc')
            ->first();
    }
}
