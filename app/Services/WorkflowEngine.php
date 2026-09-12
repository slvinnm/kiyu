<?php

namespace App\Services;

use App\Enums\Priority;
use App\Enums\QueueEventType;
use App\Enums\QueueStatus;
use App\Enums\VisitStatus;
use App\Enums\VisitWorkflowStatus;
use App\Enums\VisitWorkflowStepStatus;
use App\Models\QueueTicket;
use App\Models\Visit;
use App\Models\VisitWorkflow;
use App\Models\VisitWorkflowStep;
use App\Models\WorkflowStep;
use Illuminate\Support\Facades\DB;

class WorkflowEngine
{
    public function createFromIntake(Visit $visit, int $initialStepSequence): ?QueueTicket
    {
        return DB::transaction(function () use ($visit, $initialStepSequence) {
            $lockedVisit = Visit::whereKey($visit->id)
                ->lockForUpdate()
                ->firstOrFail();

            $workflowVersion = $lockedVisit->workflowVersion;
            $initialStep = $workflowVersion->steps()
                ->where('sequence', $initialStepSequence)
                ->first();

            if (! $initialStep) {
                return null;
            }

            $visitWorkflow = VisitWorkflow::firstOrCreate([
                'visit_id' => $lockedVisit->id,
                'workflow_version_id' => $workflowVersion->id,
            ], [
                'status' => VisitWorkflowStatus::ACTIVE->value,
            ]);

            /*
             * The first execution of the initial workflow step is always
             * execution number 1. This lookup makes intake idempotent.
             */
            $existingStep = VisitWorkflowStep::where('visit_workflow_id', $visitWorkflow->id)
                ->where('workflow_step_id', $initialStep->id)
                ->where('execution_number', 1)
                ->first();

            if ($existingStep) {
                return QueueTicket::where('visit_workflow_step_id', $existingStep->id)
                    ->where('status', QueueStatus::CREATED->value)
                    ->first();
            }

            $ticket = $this->createStepsUntilQueue(
                $lockedVisit,
                $visitWorkflow,
                $initialStep
            );

            if (! $ticket) {
                $this->completeWorkflow($lockedVisit, $visitWorkflow);
            }

            return $ticket;
        });
    }

    public function completeCurrentStep(QueueTicket $ticket): ?array
    {
        $visitWorkflowStep = VisitWorkflowStep::query()
            ->whereKey($ticket->visit_workflow_step_id)
            ->lockForUpdate()
            ->firstOrFail();
        $visit = Visit::query()->whereKey($ticket->visit_id)->lockForUpdate()->firstOrFail();

        $visitWorkflowStep->update([
            'status' => VisitWorkflowStepStatus::COMPLETED->value,
            'completed_at' => now(),
        ]);

        $workflowVersion = $visit->workflowVersion;
        $steps = $workflowVersion->steps()->get();
        $currentSequence = $visitWorkflowStep->workflowStep->sequence;

        $visitWorkflow = VisitWorkflow::query()
            ->where('visit_id', $visit->id)
            ->where('workflow_version_id', $workflowVersion->id)
            ->lockForUpdate()
            ->firstOrFail();

        $nextStep = null;

        foreach ($steps as $step) {
            if ($step->sequence > $currentSequence) {
                $nextStep = $step;
                break;
            }
        }

        if (! $nextStep) {
            $this->completeWorkflow($visit, $visitWorkflow);

            return [
                'next_step' => null,
                'visit_completed' => true,
                'ticket' => $ticket->fresh(),
            ];
        }

        $nextTicket = $this->createStepsUntilQueue($visit, $visitWorkflow, $nextStep);

        if (! $nextTicket) {
            $this->completeWorkflow($visit, $visitWorkflow);

            return [
                'next_step' => null,
                'visit_completed' => true,
                'ticket' => $ticket->fresh(),
            ];
        }

        return [
            'next_step' => $nextTicket->visitWorkflowStep->workflowStep,
            'visit_completed' => false,
            'ticket' => $nextTicket->fresh(),
        ];
    }

    private function resolvePriorityForStep(
        Visit $visit,
        ?WorkflowStep $step = null
    ): int {
        return $visit->priority instanceof Priority
            ? $visit->priority->value
            : ($visit->priority ?? Priority::NORMAL->value);
    }

    public function allocateExecutionNumber(
        VisitWorkflow $workflow,
        ?WorkflowStep $step = null
    ): int {
        $lockedWorkflow = VisitWorkflow::whereKey($workflow->id)
            ->lockForUpdate()
            ->firstOrFail();

        $query = VisitWorkflowStep::where(
            'visit_workflow_id',
            $lockedWorkflow->id
        );

        if ($step) {
            $query->where('workflow_step_id', $step->id);
        }

        $maxExecution = $query->max('execution_number') ?? 0;

        return $maxExecution + 1;
    }

    public function createOrUpdateVisitWorkflowStep(
        VisitWorkflow $workflow,
        WorkflowStep $step
    ): VisitWorkflowStep {
        /**
         * Must be called inside an active database transaction.
         * The caller owns the transaction boundary.
         */
        $executionNumber = $this->allocateExecutionNumber(
            $workflow,
            $step
        );

        return VisitWorkflowStep::create([
            'visit_workflow_id' => $workflow->id,
            'workflow_step_id' => $step->id,
            'execution_number' => $executionNumber,
            'status' => VisitWorkflowStepStatus::PENDING->value,
        ]);
    }

    private function createStepsUntilQueue(
        Visit $visit,
        VisitWorkflow $visitWorkflow,
        WorkflowStep $step
    ): ?QueueTicket {
        $steps = $visit->workflowVersion->steps()
            ->where('sequence', '>=', $step->sequence)
            ->get();

        foreach ($steps as $workflowStep) {
            if ($workflowStep->requires_queue && ! $workflowStep->station) {
                throw new \LogicException(
                    "Workflow step '{$workflowStep->name}' (sequence {$workflowStep->sequence}) requires a queue but has no station."
                );
            }

            $visitWorkflowStep = $this->createOrUpdateVisitWorkflowStep(
                $visitWorkflow,
                $workflowStep
            );

            if (! $workflowStep->requires_queue) {
                $visitWorkflowStep->update([
                    'status' => VisitWorkflowStepStatus::COMPLETED->value,
                    'completed_at' => now(),
                ]);

                continue;
            }

            $allocation = (new QueueNumberGenerator)->allocate($workflowStep->station);
            $ticket = QueueTicket::create([
                'visit_id' => $visit->id,
                'visit_workflow_step_id' => $visitWorkflowStep->id,
                'station_id' => $workflowStep->station_id,
                'queue_number' => $allocation['queue_number'],
                'priority' => $this->resolvePriorityForStep($visit, $workflowStep),
                'internal_sequence' => $allocation['internal_sequence'],
                'status' => QueueStatus::CREATED->value,
            ]);

            $ticket->events()->create([
                'event_type' => QueueEventType::CREATED,
                'from_status' => null,
                'to_status' => QueueStatus::CREATED->value,
            ]);

            return $ticket;
        }

        return null;
    }

    private function completeWorkflow(Visit $visit, VisitWorkflow $visitWorkflow): void
    {
        $visit->update([
            'status' => VisitStatus::COMPLETED->value,
            'completed_at' => now(),
        ]);
        $visitWorkflow->update(['status' => VisitWorkflowStatus::COMPLETED->value]);
    }
}
