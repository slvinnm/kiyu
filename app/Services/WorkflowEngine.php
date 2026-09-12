<?php

namespace App\Services;

use App\Enums\IntakeChannel;
use App\Enums\Priority;
use App\Enums\QueueStatus;
use App\Enums\VisitStatus;
use App\Enums\VisitWorkflowStatus;
use App\Enums\VisitWorkflowStepStatus;
use App\Models\Department;
use App\Models\Visit;
use App\Models\WorkflowVersion;
use App\Models\WorkflowStep;
use App\Models\VisitWorkflow;
use App\Models\VisitWorkflowStep;
use App\Models\QueueTicket;
use Illuminate\Support\Facades\DB;

class WorkflowEngine
{
    public function createFromIntake(Visit $visit, int $initialStepSequence): ?QueueTicket
    {
        return DB::transaction(function () use ($visit, $initialStepSequence) {
            // 1. Establish visit workflow context
            $workflowVersion = $visit->workflowVersion;
            $workflowSteps = $workflowVersion->steps();

            // 2. Get the initial step
            $initialStep = $workflowSteps->firstWhere('sequence', $initialStepSequence);
            if (! $initialStep) {
                return null;
            }

            // 3. Create or find the visit workflow step with concurrency-safe locking
            // Lock the visit row to prevent concurrent workflow creation for same visit
            $lockedVisit = Visit::whereKey($visit->id)
                ->lockForUpdate()
                ->firstOrFail();

            $visitWorkflow = VisitWorkflow::firstOrCreate([
                'visit_id' => $lockedVisit->id,
                'workflow_version_id' => $workflowVersion->id,
            ], [
                'status' => VisitWorkflowStatus::ACTIVE->value,
            ]);

            // Check if initial workflow step already exists for this visit/workflow/step
            $existingStep = VisitWorkflowStep::where('visit_workflow_id', $visitWorkflow->id)
                ->where('workflow_step_id', $initialStep->id)
                ->where('execution_number', 1)
                ->first();

            if ($existingStep) {
                // Idempotent: do not create a second initial execution; return existing state if queued
                $existingTicket = QueueTicket::where('visit_workflow_step_id', $existingStep->id)
                    ->where('status', QueueStatus::CREATED->value)
                    ->first();
                return $existingTicket;
            }

            $visitWorkflowStep = VisitWorkflowStep::create([
                'visit_workflow_id' => $visitWorkflow->id,
                'workflow_step_id' => $initialStep->id,
                'execution_number' => 1,
                'status' => VisitWorkflowStepStatus::PENDING->value,
            ]);

            // 4. If the step requires a queue, create a queue ticket
            if ($initialStep->requires_queue) {
                $station = $initialStep->station;
                if ($station) {
                    $allocation = (new QueueNumberGenerator())->allocate($station);

                    $ticket = QueueTicket::create([
                        'visit_id' => $visit->id,
                        'visit_workflow_step_id' => $visitWorkflowStep->id,
                        'station_id' => $station->id,
                        'queue_number' => $allocation['queue_number'],
                        'priority' => $this->resolvePriorityForStep($visit, $initialStep),
                        'internal_sequence' => $allocation['internal_sequence'],
                        'status' => QueueStatus::CREATED->value,
                    ]);

                    // Log creation event
                    $ticket->events()->create([
                        'event_type' => \App\Enums\QueueEventType::CREATED,
                        'from_status' => null,
                        'to_status' => QueueStatus::CREATED->value,
                    ]);

                    return $ticket;
                }
            }

            return null;
        });
    }

    public function completeCurrentStep(QueueTicket $ticket): ?array
    {
        return DB::transaction(function () use ($ticket) {
            $visitWorkflowStep = $ticket->visitWorkflowStep;
            $visit = $ticket->visit;

            // 1. Mark visit workflow step as completed
            $visitWorkflowStep->update([
                'status' => \App\Enums\VisitWorkflowStepStatus::COMPLETED->value,
                'completed_at' => now(),
            ]);

            // 2. QueueTicket state already set to COMPLETED by QueueService::completeTicket's state machine.
            //    WorkflowEngine should not re-mutate QueueTicket status.

            // 3. Process all consecutive non-queue steps automatically, then find the next queue step
            $workflowVersion = $visit->workflowVersion;
            $steps = $workflowVersion->steps();
            $currentSequence = $visitWorkflowStep->workflowStep->sequence;

            // Collect all steps after current, in sequence order
            $remainingSteps = $steps->where('sequence', '>', $currentSequence)
                ->sortBy('sequence')
                ->values()
                ->all();

            // Process all consecutive non-queue steps from the start
            foreach ($remainingSteps as $step) {
                if ($step->requires_queue) {
                    // Found a queue-requiring step — stop processing non-queue steps
                    break;
                } else {
                    // Non-queue step: create VisitWorkflowStep execution and mark completed
                    $visitWorkflow = VisitWorkflow::firstOrCreate([
                        'visit_id' => $visit->id,
                        'workflow_version_id' => $workflowVersion->id,
                    ], [
                        'status' => VisitWorkflowStatus::ACTIVE->value,
                    ]);

                    $executionNumber = VisitWorkflowStep::where('visit_workflow_id', $visitWorkflow->id)
                        ->where('workflow_step_id', $step->id)
                        ->max('execution_number') ?? 0;

                    $nonQueueStep = VisitWorkflowStep::create([
                        'visit_workflow_id' => $visitWorkflow->id,
                        'workflow_step_id' => $step->id,
                        'execution_number' => $executionNumber + 1,
                        'status' => VisitWorkflowStepStatus::PENDING->value,
                    ]);

                    // Mark non-queue step as completed immediately
                    $nonQueueStep->update([
                        'status' => VisitWorkflowStepStatus::COMPLETED->value,
                        'completed_at' => now(),
                    ]);
                }
            }

            // Now find the first queue-requiring step from remaining
            $nextStep = null;
            foreach ($remainingSteps as $step) {
                if ($step->requires_queue) {
                    $nextStep = $step;
                    break;
                }
            }

            // 5. Check for completion — if no next step at all, mark visit complete
            if (! $nextStep) {
                // No more steps, mark visit as complete
                $visit->update([
                    'status' => \App\Enums\VisitStatus::COMPLETED->value,
                    'completed_at' => now(),
                ]);

                return ['next_step' => null, 'visit_completed' => true, 'ticket' => $ticket->fresh()];
            }

            // 6. Create next visit workflow step with proper execution number allocation
            $visitWorkflow = VisitWorkflow::firstOrCreate([
                'visit_id' => $visit->id,
                'workflow_version_id' => $workflowVersion->id,
            ], [
                'status' => VisitWorkflowStatus::ACTIVE->value,
            ]);

            $executionNumber = $this->allocateExecutionNumber($visitWorkflow, $nextStep);

            $nextVisitWorkflowStep = VisitWorkflowStep::create([
                'visit_workflow_id' => $visitWorkflow->id,
                'workflow_step_id' => $nextStep->id,
                'execution_number' => $executionNumber,
                'status' => VisitWorkflowStepStatus::PENDING->value,
            ]);

            // 7. Create next queue ticket if step requires queue
            if ($nextStep->requires_queue) {
                $station = $nextStep->station;
                if ($station) {
                    $allocation = (new QueueNumberGenerator())->allocate($station);

                    $nextTicket = QueueTicket::create([
                        'visit_id' => $visit->id,
                        'visit_workflow_step_id' => $nextVisitWorkflowStep->id,
                        'station_id' => $station->id,
                        'queue_number' => $allocation['queue_number'],
                        'priority' => $this->resolvePriorityForStep($visit, $nextStep),
                        'internal_sequence' => $allocation['internal_sequence'],
                        'status' => \App\Enums\QueueStatus::CREATED->value,
                    ]);

                    // Log creation event
                    $nextTicket->events()->create([
                        'event_type' => \App\Enums\QueueEventType::CREATED,
                        'from_status' => null,
                        'to_status' => \App\Enums\QueueStatus::CREATED->value,
                    ]);

                    return ['next_step' => $nextStep, 'visit_completed' => false, 'ticket' => $nextTicket->fresh()];
                }
            }

            return ['next_step' => $nextStep, 'visit_completed' => false, 'ticket' => $nextVisitWorkflowStep->fresh()];
        });
    }

    /**
     * Centralized execution-number allocation for VisitWorkflowStep.
     * Locks the parent VisitWorkflow row to serialize concurrent execution.
     * Returns the next execution number for the given step.
     */
    private function allocateExecutionNumber(VisitWorkflow $workflow, WorkflowStep $step): int
    {
        return DB::transaction(function () use ($workflow, $step) {
            // Lock the parent workflow row — this serializes all execution
            // number allocations for steps within this visit workflow.
            $lockedWorkflow = VisitWorkflow::whereKey($workflow->id)
                ->lockForUpdate()
                ->firstOrFail();

            $maxExecution = VisitWorkflowStep::where('visit_workflow_id', $lockedWorkflow->id)
                ->where('workflow_step_id', $step->id)
                ->max('execution_number') ?? 0;

            return $maxExecution + 1;
        });
    }

    private function resolvePriorityForStep(Visit $visit, ?WorkflowStep $step = null): int
    {
        // Return visit's durable priority; default to NORMAL if not set
        return $visit->priority ?? Priority::NORMAL->value;
    }

    public function createOrUpdateVisitWorkflowStep(VisitWorkflow $workflow, WorkflowStep $step): VisitWorkflowStep
    {
        // For repeatable steps, we must find next execution number rather than updateOrCreate
        // Lock the parent VisitWorkflow row via query (not model instance) to serialize
        // all execution-number allocations for steps within this visit workflow.
        $lockedWorkflow = VisitWorkflow::whereKey($workflow->id)
            ->lockForUpdate()
            ->firstOrFail();

        $maxExecution = VisitWorkflowStep::where('visit_workflow_id', $lockedWorkflow->id)
            ->where('workflow_step_id', $step->id)
            ->max('execution_number') ?? 0;

        return VisitWorkflowStep::create([
            'visit_workflow_id' => $lockedWorkflow->id,
            'workflow_step_id' => $step->id,
            'execution_number' => $maxExecution + 1,
            'status' => VisitWorkflowStepStatus::PENDING->value,
        ]);
    }
}