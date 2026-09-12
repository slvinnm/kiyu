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
use Illuminate\Validation\ValidationException;

class WorkflowEngine
{
    public function __construct(
        private ?WorkflowRequirementEvaluator $requirementEvaluator = null,
    ) {
        $this->requirementEvaluator ??= new WorkflowRequirementEvaluator;
    }

    public function createFromIntake(
        Visit $visit,
        int $initialStepSequence,
        array $context = [],
    ): ?QueueTicket {
        return DB::transaction(function () use ($visit, $initialStepSequence, $context) {
            $lockedVisit = Visit::query()
                ->whereKey($visit->id)
                ->lockForUpdate()
                ->firstOrFail();

            $workflowVersion = $lockedVisit->workflowVersion;

            $initialStep = $workflowVersion->steps()
                ->where('sequence', '>=', $initialStepSequence)
                ->orderBy('sequence')
                ->first();

            if (! $initialStep) {
                return null;
            }

            $visitWorkflow = VisitWorkflow::query()->firstOrCreate([
                'visit_id' => $lockedVisit->id,
                'workflow_version_id' => $workflowVersion->id,
            ], [
                'status' => VisitWorkflowStatus::ACTIVE->value,
            ]);

            $existingInitialStep = VisitWorkflowStep::query()
                ->where('visit_workflow_id', $visitWorkflow->id)
                ->where('workflow_step_id', $initialStep->id)
                ->where('execution_number', 1)
                ->first();

            if ($existingInitialStep) {
                return $existingInitialStep->queueTickets()
                    ->where('status', QueueStatus::CREATED->value)
                    ->latest('id')
                    ->first();
            }

            return $this->advanceUntilQueue(
                visit: $lockedVisit,
                visitWorkflow: $visitWorkflow,
                fromSequence: $initialStep->sequence,
                context: $this->mergeContext($this->baseContext($lockedVisit), $context),
            );
        });
    }

    public function completeCurrentStep(
        QueueTicket $ticket,
        array $context = [],
    ): array {
        $visit = Visit::query()
            ->whereKey($ticket->visit_id)
            ->lockForUpdate()
            ->firstOrFail();

        $visitWorkflow = VisitWorkflow::query()
            ->where('visit_id', $visit->id)
            ->where('workflow_version_id', $visit->workflow_version_id)
            ->lockForUpdate()
            ->firstOrFail();

        $visitWorkflowStep = VisitWorkflowStep::query()
            ->whereKey($ticket->visit_workflow_step_id)
            ->lockForUpdate()
            ->firstOrFail();

        $workflowStep = $visitWorkflowStep->workflowStep;
        $evaluationContext = $this->mergeContext(
            $this->baseContext($visit, $ticket, $visitWorkflowStep, $workflowStep),
            $context,
        );

        if (! $this->requirementEvaluator->passes($workflowStep->completion_requirements, $evaluationContext)) {
            throw ValidationException::withMessages([
                'completion_requirements' => "Workflow step '{$workflowStep->name}' cannot be completed because its completion requirements are not satisfied.",
            ]);
        }

        $visitWorkflowStep->update([
            'status' => VisitWorkflowStepStatus::COMPLETED->value,
            'completed_at' => now(),
        ]);

        if ($workflowStep->is_repeatable && ($context['repeat_current_step'] ?? false) === true) {
            $repeatTicket = $this->createStepExecution(
                visit: $visit,
                visitWorkflow: $visitWorkflow,
                workflowStep: $workflowStep,
            );

            $visit->update(['status' => VisitStatus::WAITING->value]);

            return [
                'next_step' => $workflowStep,
                'visit_completed' => false,
                'ticket' => $repeatTicket,
                'repeated' => true,
            ];
        }

        $nextTicket = $this->advanceUntilQueue(
            visit: $visit,
            visitWorkflow: $visitWorkflow,
            fromSequence: $workflowStep->sequence + 1,
            context: $evaluationContext,
        );

        if ($nextTicket) {
            $visit->update(['status' => VisitStatus::WAITING->value]);

            return [
                'next_step' => $nextTicket->visitWorkflowStep->workflowStep,
                'visit_completed' => false,
                'ticket' => $nextTicket->fresh(),
                'repeated' => false,
            ];
        }

        $this->completeWorkflow($visit, $visitWorkflow);

        return [
            'next_step' => null,
            'visit_completed' => true,
            'ticket' => $ticket->fresh(),
            'repeated' => false,
        ];
    }

    public function skipCurrentStep(
        QueueTicket $ticket,
        ?string $reason = null,
        array $context = [],
    ): array {
        $visit = Visit::query()
            ->whereKey($ticket->visit_id)
            ->lockForUpdate()
            ->firstOrFail();

        $visitWorkflow = VisitWorkflow::query()
            ->where('visit_id', $visit->id)
            ->where('workflow_version_id', $visit->workflow_version_id)
            ->lockForUpdate()
            ->firstOrFail();

        $visitWorkflowStep = VisitWorkflowStep::query()
            ->whereKey($ticket->visit_workflow_step_id)
            ->lockForUpdate()
            ->firstOrFail();

        $workflowStep = $visitWorkflowStep->workflowStep;

        if (! $workflowStep->can_skip) {
            throw ValidationException::withMessages([
                'ticket' => "Workflow step '{$workflowStep->name}' cannot be skipped.",
            ]);
        }

        $visitWorkflowStep->update([
            'status' => VisitWorkflowStepStatus::SKIPPED->value,
            'completed_at' => now(),
        ]);

        if ($reason) {
            $ticket->update([
                'notes' => $reason.($ticket->notes ? " | {$ticket->notes}" : ''),
            ]);
        }

        $nextTicket = $this->advanceUntilQueue(
            visit: $visit,
            visitWorkflow: $visitWorkflow,
            fromSequence: $workflowStep->sequence + 1,
            context: $this->mergeContext(
                $this->baseContext($visit, $ticket, $visitWorkflowStep, $workflowStep),
                $context,
            ),
        );

        if ($nextTicket) {
            $visit->update(['status' => VisitStatus::WAITING->value]);

            return [
                'next_step' => $nextTicket->visitWorkflowStep->workflowStep,
                'visit_completed' => false,
                'ticket' => $nextTicket->fresh(),
            ];
        }

        $this->completeWorkflow($visit, $visitWorkflow);

        return [
            'next_step' => null,
            'visit_completed' => true,
            'ticket' => $ticket->fresh(),
        ];
    }

    public function allocateExecutionNumber(
        VisitWorkflow $workflow,
        ?WorkflowStep $step = null,
    ): int {
        $lockedWorkflow = VisitWorkflow::query()
            ->whereKey($workflow->id)
            ->lockForUpdate()
            ->firstOrFail();

        $query = VisitWorkflowStep::query()
            ->where('visit_workflow_id', $lockedWorkflow->id);

        if ($step) {
            $query->where('workflow_step_id', $step->id);
        }

        return ($query->max('execution_number') ?? 0) + 1;
    }

    private function advanceUntilQueue(
        Visit $visit,
        VisitWorkflow $visitWorkflow,
        int $fromSequence,
        array $context,
    ): ?QueueTicket {
        $steps = $visit->workflowVersion->steps()
            ->where('sequence', '>=', $fromSequence)
            ->orderBy('sequence')
            ->get();

        foreach ($steps as $workflowStep) {
            if (! $this->isStepApplicable($workflowStep, $context)) {
                $this->createSkippedStep($visitWorkflow, $workflowStep);

                continue;
            }

            if (! $workflowStep->requires_queue) {
                $runtimeStep = $this->createStepExecution(
                    visit: $visit,
                    visitWorkflow: $visitWorkflow,
                    workflowStep: $workflowStep,
                    createQueue: false,
                );

                if (! $this->requirementEvaluator->passes($workflowStep->completion_requirements, $context)) {
                    if ($workflowStep->is_optional) {
                        $runtimeStep->update([
                            'status' => VisitWorkflowStepStatus::SKIPPED->value,
                            'completed_at' => now(),
                        ]);

                        continue;
                    }

                    throw ValidationException::withMessages([
                        'completion_requirements' => "Workflow step '{$workflowStep->name}' cannot complete because its completion requirements are not satisfied.",
                    ]);
                }

                $runtimeStep->update([
                    'status' => VisitWorkflowStepStatus::COMPLETED->value,
                    'completed_at' => now(),
                ]);

                continue;
            }

            return $this->createStepExecution(
                visit: $visit,
                visitWorkflow: $visitWorkflow,
                workflowStep: $workflowStep,
            );
        }

        return null;
    }

    private function isStepApplicable(WorkflowStep $step, array $context): bool
    {
        return $this->requirementEvaluator->passes($step->entry_conditions, $context);
    }

    private function createStepExecution(
        Visit $visit,
        VisitWorkflow $visitWorkflow,
        WorkflowStep $workflowStep,
        bool $createQueue = true,
    ): ?QueueTicket {
        if ($workflowStep->requires_queue && ! $workflowStep->station) {
            throw new \LogicException(
                "Workflow step '{$workflowStep->name}' requires a queue but has no station."
            );
        }

        $executionNumber = $this->allocateExecutionNumber($visitWorkflow, $workflowStep);

        $runtimeStep = VisitWorkflowStep::create([
            'visit_workflow_id' => $visitWorkflow->id,
            'workflow_step_id' => $workflowStep->id,
            'execution_number' => $executionNumber,
            'status' => VisitWorkflowStepStatus::PENDING->value,
        ]);

        if (! $createQueue || ! $workflowStep->requires_queue) {
            return null;
        }

        $allocation = (new QueueNumberGenerator)->allocate($workflowStep->station);

        $ticket = QueueTicket::create([
            'visit_id' => $visit->id,
            'visit_workflow_step_id' => $runtimeStep->id,
            'station_id' => $workflowStep->station_id,
            'queue_number' => $allocation['queue_number'],
            'priority' => $this->resolvePriorityForStep($visit),
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

    private function createSkippedStep(
        VisitWorkflow $visitWorkflow,
        WorkflowStep $workflowStep,
    ): VisitWorkflowStep {
        $executionNumber = $this->allocateExecutionNumber($visitWorkflow, $workflowStep);

        return VisitWorkflowStep::create([
            'visit_workflow_id' => $visitWorkflow->id,
            'workflow_step_id' => $workflowStep->id,
            'execution_number' => $executionNumber,
            'status' => VisitWorkflowStepStatus::SKIPPED->value,
            'completed_at' => now(),
        ]);
    }

    private function resolvePriorityForStep(Visit $visit): int
    {
        return $visit->priority instanceof Priority
            ? $visit->priority->value
            : ($visit->priority ?? Priority::NORMAL->value);
    }

    private function baseContext(
        Visit $visit,
        ?QueueTicket $ticket = null,
        ?VisitWorkflowStep $visitWorkflowStep = null,
        ?WorkflowStep $workflowStep = null,
    ): array {
        return [
            'visit' => $visit->toArray(),
            'ticket' => $ticket?->toArray() ?? [],
            'runtime_step' => $visitWorkflowStep?->toArray() ?? [],
            'workflow_step' => $workflowStep?->toArray() ?? [],
        ];
    }

    private function mergeContext(array $base, array $context): array
    {
        return array_replace_recursive($base, $context);
    }

    private function completeWorkflow(Visit $visit, VisitWorkflow $visitWorkflow): void
    {
        $visit->update([
            'status' => VisitStatus::COMPLETED->value,
            'completed_at' => now(),
        ]);

        $visitWorkflow->update([
            'status' => VisitWorkflowStatus::COMPLETED->value,
        ]);
    }
}
