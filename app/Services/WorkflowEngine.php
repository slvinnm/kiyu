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

            // 3. Create or find the visit workflow step (idempotent for initial creation)
            $visitWorkflow = VisitWorkflow::firstOrCreate([
                'visit_id' => $visit->id,
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

            // 3. Determine next applicable step
            $workflowVersion = $visit->workflowVersion;
            $steps = $workflowVersion->steps();
            $currentSequence = $visitWorkflowStep->workflowStep->sequence;

            $nextStep = null;
            foreach ($steps as $step) {
                if ($step->sequence > $currentSequence) {
                    // Optional steps can be skipped; only pick them if not skipped.
                    // For now, proceed to first non-optional or optional step after current.
                    $nextStep = $step;
                    break;
                }
            }

            // Also check for optional steps that might come earlier
            if (! $nextStep) {
                // Check for optional steps that were skipped past
                $nextStep = $steps->firstWhere(fn($s) => $s->is_optional && $s->sequence > $currentSequence);
            }

            // 5. Check for completion
            if (! $nextStep) {
                // No more steps, mark visit as complete
                $visit->update([
                    'status' => \App\Enums\VisitStatus::COMPLETED->value,
                    'completed_at' => now(),
                ]);

                return ['next_step' => null, 'visit_completed' => true, 'ticket' => $ticket->fresh()];
            }

            // 6. Create next visit workflow step
            $visitWorkflow = VisitWorkflow::firstOrCreate([
                'visit_id' => $visit->id,
                'workflow_version_id' => $workflowVersion->id,
            ], [
                'status' => VisitWorkflowStatus::ACTIVE->value,
            ]);

            $nextVisitWorkflowStep = VisitWorkflowStep::create([
                'visit_workflow_id' => $visitWorkflow->id,
                'workflow_step_id' => $nextStep->id,
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

    private function resolvePriorityForStep(Visit $visit, ?WorkflowStep $step = null): int
    {
        // Return visit's durable priority; default to NORMAL if not set
        return $visit->priority ?? Priority::NORMAL->value;
    }

    public function createOrUpdateVisitWorkflowStep(VisitWorkflow $workflow, WorkflowStep $step): VisitWorkflowStep
    {
        // For repeatable steps, we must find next execution number rather than updateOrCreate.
        // Lock the workflow to serialize concurrent executions of the same step.
        return DB::transaction(function () use ($workflow, $step) {
            $workflow->lockForUpdate();

            $maxExecution = VisitWorkflowStep::where('visit_workflow_id', $workflow->id)
                ->where('workflow_step_id', $step->id)
                ->max('execution_number') ?? 0;

            return VisitWorkflowStep::create([
                'visit_workflow_id' => $workflow->id,
                'workflow_step_id' => $step->id,
                'execution_number' => $maxExecution + 1,
                'status' => VisitWorkflowStepStatus::PENDING->value,
            ]);
        });
    }
}