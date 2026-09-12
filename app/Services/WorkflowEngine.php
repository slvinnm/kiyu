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
            $workflowVersion = $visit->workflowVersion;
            $workflowSteps = $workflowVersion->steps();

            $initialStep = $workflowSteps->firstWhere('sequence', $initialStepSequence);
            if (! $initialStep) {
                return null;
            }

            $lockedVisit = Visit::whereKey($visit->id)
                ->lockForUpdate()
                ->firstOrFail();

            $visitWorkflow = VisitWorkflow::firstOrCreate([
                'visit_id' => $lockedVisit->id,
                'workflow_version_id' => $workflowVersion->id,
            ], [
                'status' => VisitWorkflowStatus::ACTIVE->value,
            ]);

            $existingStep = VisitWorkflowStep::where('visit_workflow_id', $visitWorkflow->id)
                ->where('workflow_step_id', $initialStep->id)
                ->where('execution_number', 1)
                ->first();

            if ($existingStep) {
                $existingTicket = QueueTicket::where('visit_workflow_step_id', $existingStep->id)
                    ->where('status', QueueStatus::CREATED->value)
                    ->first();
                return $existingTicket;
            }

            $executionNumber = $this->allocateExecutionNumber($visitWorkflow, $initialStep);

            $visitWorkflowStep = VisitWorkflowStep::create([
                'visit_workflow_id' => $visitWorkflow->id,
                'workflow_step_id' => $initialStep->id,
                'execution_number' => $executionNumber,
                'status' => VisitWorkflowStepStatus::PENDING->value,
            ]);

            if ($initialStep->requires_queue) {
                $station = $initialStep->station;
                if ($station) {
                    $allocation = (new QueueNumberGenerator())->allocate($station);
                    $ticket = QueueTicket::create([
                        'visit_id' => $lockedVisit->id,
                        'visit_workflow_step_id' => $visitWorkflowStep->id,
                        'station_id' => $station->id,
                        'queue_number' => $allocation['queue_number'],
                        'priority' => $this->resolvePriorityForStep($lockedVisit, $initialStep),
                        'internal_sequence' => $allocation['internal_sequence'],
                        'status' => QueueStatus::CREATED->value,
                    ]);
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

            $visitWorkflowStep->update([
                'status' => VisitWorkflowStepStatus::COMPLETED->value,
                'completed_at' => now(),
            ]);

            $workflowVersion = $visit->workflowVersion;
            $steps = $workflowVersion->steps();
            $currentSequence = $visitWorkflowStep->workflowStep->sequence;

            $visitWorkflow = VisitWorkflow::firstOrCreate([
                'visit_id' => $visit->id,
                'workflow_version_id' => $workflowVersion->id,
            ], [
                'status' => VisitWorkflowStatus::ACTIVE->value,
            ]);

            while (true) {
                $nextStep = null;
                foreach ($steps as $step) {
                    if ($step->sequence > $currentSequence) {
                        $nextStep = $step;
                        $currentSequence = $step->sequence;
                        break;
                    }
                }

                if (! $nextStep) {
                    $visit->update([
                        'status' => VisitStatus::COMPLETED->value,
                        'completed_at' => now(),
                    ]);
                    return ['next_step' => null, 'visit_completed' => true, 'ticket' => $ticket->fresh()];
                }

                $executionNumber = $this->allocateExecutionNumber($visitWorkflow, $nextStep);

                $visitWorkflowStepRecord = VisitWorkflowStep::create([
                    'visit_workflow_id' => $visitWorkflow->id,
                    'workflow_step_id' => $nextStep->id,
                    'execution_number' => $executionNumber,
                    'status' => VisitWorkflowStepStatus::PENDING->value,
                ]);

                if ($nextStep->requires_queue) {
                    $station = $nextStep->station;
                    if ($station) {
                        $allocation = (new QueueNumberGenerator())->allocate($station);
                        $nextTicket = QueueTicket::create([
                            'visit_id' => $visit->id,
                            'visit_workflow_step_id' => $visitWorkflowStepRecord->id,
                            'station_id' => $station->id,
                            'queue_number' => $allocation['queue_number'],
                            'priority' => $this->resolvePriorityForStep($visit, $nextStep),
                            'internal_sequence' => $allocation['internal_sequence'],
                            'status' => QueueStatus::CREATED->value,
                        ]);
                        $nextTicket->events()->create([
                            'event_type' => \App\Enums\QueueEventType::CREATED,
                            'from_status' => null,
                            'to_status' => QueueStatus::CREATED->value,
                        ]);
                        return ['next_step' => $nextStep, 'visit_completed' => false, 'ticket' => $nextTicket->fresh()];
                    }
                    return ['next_step' => $nextStep, 'visit_completed' => false, 'ticket' => $visitWorkflowStepRecord->fresh()];
                }

                $visitWorkflowStepRecord->update([
                    'status' => VisitWorkflowStepStatus::COMPLETED->value,
                    'completed_at' => now(),
                ]);
            }
        });
    }

    private function resolvePriorityForStep(Visit $visit, ?WorkflowStep $step = null): int
    {
        return $visit->priority ?? Priority::NORMAL->value;
    }

    public function allocateExecutionNumber(VisitWorkflow $workflow, ?WorkflowStep $step = null): int
    {
        $lockedWorkflow = VisitWorkflow::whereKey($workflow->id)
            ->lockForUpdate()
            ->firstOrFail();

        $query = VisitWorkflowStep::where('visit_workflow_id', $lockedWorkflow->id);
        if ($step) {
            $query->where('workflow_step_id', $step->id);
        }
        $maxExecution = $query->max('execution_number') ?? 0;
        return $maxExecution + 1;
    }

    public function createOrUpdateVisitWorkflowStep(VisitWorkflow $workflow, WorkflowStep $step): VisitWorkflowStep
    {
        $lockedWorkflow = VisitWorkflow::whereKey($workflow->id)
            ->lockForUpdate()
            ->firstOrFail();

        $executionNumber = $this->allocateExecutionNumber($lockedWorkflow, $step);

        return VisitWorkflowStep::create([
            'visit_workflow_id' => $lockedWorkflow->id,
            'workflow_step_id' => $step->id,
            'execution_number' => $executionNumber,
            'status' => VisitWorkflowStepStatus::PENDING->value,
        ]);
    }
}
