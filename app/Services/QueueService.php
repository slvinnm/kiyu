<?php

namespace App\Services;

use App\Enums\QueueEventType;
use App\Enums\QueueStatus;
use App\Enums\VisitStatus;
use App\Enums\VisitWorkflowStatus;
use App\Enums\VisitWorkflowStepStatus;
use App\Models\AuditLog;
use App\Models\QueueTicket;
use App\Models\Station;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class QueueService
{
    protected QueueStateMachine $stateMachine;

    protected QueueSelector $selector;

    protected WorkflowEngine $workflowEngine;

    public function __construct(
        ?QueueStateMachine $stateMachine = null,
        ?QueueSelector $selector = null,
        ?WorkflowEngine $workflowEngine = null
    ) {
        $this->stateMachine = $stateMachine ?? new QueueStateMachine;
        $this->selector = $selector ?? new QueueSelector;
        $this->workflowEngine = $workflowEngine ?? new WorkflowEngine;
    }

    public function callNext(int $stationId, ?int $calledByUserId = null): ?QueueTicket
    {
        return DB::transaction(function () use ($stationId, $calledByUserId) {
            $station = Station::query()
                ->whereKey($stationId)
                ->lockForUpdate()
                ->firstOrFail();

            $hasActiveTicket = QueueTicket::query()
                ->where('station_id', $station->id)
                ->whereIn('status', [
                    QueueStatus::CALLED->value,
                    QueueStatus::IN_PROGRESS->value,
                    QueueStatus::ON_HOLD->value,
                ])
                ->exists();

            if ($hasActiveTicket) {
                throw ValidationException::withMessages([
                    'station' => 'The station already has an active queue ticket.',
                ]);
            }

            $ticket = $this->selector->callNext($station);

            if (! $ticket) {
                return null;
            }

            $this->stateMachine->apply($ticket, QueueStatus::CALLED, $calledByUserId);

            return $ticket->fresh();
        });
    }

    public function startTicket(int $ticketId, ?int $startedByUserId = null): bool
    {
        return DB::transaction(function () use ($ticketId, $startedByUserId) {
            $ticket = QueueTicket::whereKey($ticketId)
                ->lockForUpdate()
                ->firstOrFail();

            if (! $this->stateMachine->isEligibleToStart($ticket)) {
                return false;
            }

            $started = $this->stateMachine->apply(
                $ticket,
                QueueStatus::IN_PROGRESS,
                $startedByUserId,
            );

            if (! $started) {
                return false;
            }

            $ticket->visitWorkflowStep()->update([
                'status' => VisitWorkflowStepStatus::IN_PROGRESS->value,
                'started_at' => now(),
            ]);

            $ticket->visit()->update([
                'status' => VisitStatus::IN_PROGRESS->value,
            ]);

            return true;
        });
    }

    public function completeTicket(
        int $ticketId,
        ?int $completedByUserId = null,
        array $completionContext = [],
    ): array {
        return DB::transaction(function () use ($ticketId, $completedByUserId, $completionContext) {
            $ticket = QueueTicket::with([
                'visitWorkflowStep.visitWorkflow.visit',
                'visitWorkflowStep.workflowStep.station',
            ])
                ->whereKey($ticketId)
                ->lockForUpdate()
                ->firstOrFail();

            if (! $this->stateMachine->isEligibleForCompletion($ticket)) {
                throw ValidationException::withMessages([
                    'ticket' => 'Ticket must be IN_PROGRESS to complete.',
                ]);
            }

            $this->stateMachine->apply(
                $ticket,
                QueueStatus::COMPLETED,
                $completedByUserId,
            );

            $result = $this->workflowEngine->completeCurrentStep(
                $ticket,
                $completionContext,
            );

            return [
                'ticket' => $ticket->fresh(),
                'next_step' => $result['next_step'],
                'visit_completed' => $result['visit_completed'],
                'repeated' => $result['repeated'] ?? false,
            ];
        });
    }

    public function holdTicket(int $ticketId, ?int $heldByUserId = null): bool
    {
        return DB::transaction(function () use ($ticketId, $heldByUserId) {
            $ticket = QueueTicket::whereKey($ticketId)
                ->lockForUpdate()
                ->firstOrFail();

            if (! in_array($ticket->status->value, [
                QueueStatus::CALLED->value,
                QueueStatus::IN_PROGRESS->value,
            ], true)) {
                return false;
            }

            return $this->stateMachine->apply($ticket, QueueStatus::ON_HOLD, $heldByUserId);
        });
    }

    public function resumeTicket(int $ticketId, ?int $resumedByUserId = null): bool
    {
        return DB::transaction(function () use ($ticketId, $resumedByUserId) {
            $ticket = QueueTicket::whereKey($ticketId)
                ->lockForUpdate()
                ->firstOrFail();

            if (! $this->stateMachine->isEligibleForResume($ticket)) {
                return false;
            }

            return $this->stateMachine->apply($ticket, QueueStatus::CALLED, $resumedByUserId);
        });
    }

    public function skipTicket(
        int $ticketId,
        ?int $skippedByUserId = null,
        ?string $reason = null,
        array $context = [],
    ): array {
        return DB::transaction(function () use ($ticketId, $skippedByUserId, $reason, $context) {
            $ticket = QueueTicket::whereKey($ticketId)
                ->lockForUpdate()
                ->firstOrFail();

            if (! $this->stateMachine->isEligibleForSkip($ticket)) {
                return [
                    'skipped' => false,
                    'next_step' => null,
                    'visit_completed' => false,
                ];
            }

            $workflowStep = $ticket->visitWorkflowStep()->with('workflowStep')->firstOrFail()->workflowStep;
            if (! $workflowStep->can_skip) {
                throw ValidationException::withMessages([
                    'ticket' => "Workflow step '{$workflowStep->name}' cannot be skipped.",
                ]);
            }

            $result = $this->stateMachine->apply(
                $ticket,
                QueueStatus::SKIPPED,
                $skippedByUserId,
                [
                    'reason' => $reason,
                    'context' => $context,
                ],
            );

            if (! $result) {
                return [
                    'skipped' => false,
                    'next_step' => null,
                    'visit_completed' => false,
                ];
            }

            $workflowResult = $this->workflowEngine->skipCurrentStep(
                $ticket,
                $reason,
                $context,
            );

            return [
                'skipped' => true,
                'next_step' => $workflowResult['next_step'],
                'visit_completed' => $workflowResult['visit_completed'],
                'ticket' => $ticket->fresh(),
            ];
        });
    }

    public function markNoShow(int $ticketId, ?int $markedByUserId = null): bool
    {
        return DB::transaction(function () use ($ticketId, $markedByUserId) {
            $ticket = QueueTicket::query()
                ->whereKey($ticketId)
                ->lockForUpdate()
                ->firstOrFail();

            $updated = $this->stateMachine->apply(
                $ticket,
                QueueStatus::NO_SHOW,
                $markedByUserId,
                ['reason' => 'NO_SHOW'],
            );

            if ($updated) {
                $this->terminateTicketRuntime($ticket);
            }

            return $updated;
        });
    }

    public function cancelTicket(int $ticketId, ?int $cancelledByUserId = null): bool
    {
        return DB::transaction(function () use ($ticketId, $cancelledByUserId) {
            $ticket = QueueTicket::whereKey($ticketId)
                ->lockForUpdate()
                ->firstOrFail();

            if (! in_array($ticket->status->value, [
                QueueStatus::CREATED->value,
                QueueStatus::CALLED->value,
                QueueStatus::IN_PROGRESS->value,
                QueueStatus::ON_HOLD->value,
            ], true)) {
                return false;
            }

            $updated = $this->stateMachine->apply(
                $ticket,
                QueueStatus::CANCELLED,
                $cancelledByUserId,
                ['reason' => 'CANCELLED'],
            );

            if ($updated) {
                $this->terminateTicketRuntime($ticket);
            }

            return $updated;
        });
    }

    private function terminateTicketRuntime(QueueTicket $ticket): void
    {
        $runtimeStep = $ticket->visitWorkflowStep()
            ->lockForUpdate()
            ->firstOrFail();
        $visitWorkflow = $runtimeStep->visitWorkflow()
            ->lockForUpdate()
            ->firstOrFail();
        $visit = $visitWorkflow->visit()
            ->lockForUpdate()
            ->firstOrFail();

        $runtimeStep->update([
            'status' => VisitWorkflowStepStatus::CANCELLED->value,
            'completed_at' => now(),
        ]);

        $visitWorkflow->update([
            'status' => VisitWorkflowStatus::CANCELLED->value,
        ]);

        $visitWorkflow->steps()
            ->whereIn('status', [
                VisitWorkflowStepStatus::PENDING->value,
                VisitWorkflowStepStatus::IN_PROGRESS->value,
            ])
            ->where('id', '!=', $runtimeStep->id)
            ->update([
                'status' => VisitWorkflowStepStatus::CANCELLED->value,
                'completed_at' => now(),
            ]);

        $visit->update([
            'status' => VisitStatus::CANCELLED->value,
            'online_active_key' => null,
        ]);
    }

    public function transferTicket(int $ticketId, int $targetStationId, ?int $transferredByUserId = null): array
    {
        return DB::transaction(function () use ($ticketId, $targetStationId, $transferredByUserId) {
            $ticket = QueueTicket::with([
                'visitWorkflowStep.workflowStep.station',
                'visit',
            ])
                ->whereKey($ticketId)
                ->lockForUpdate()
                ->firstOrFail();

            $targetStation = Station::query()
                ->whereKey($targetStationId)
                ->where('is_active', true)
                ->lockForUpdate()
                ->firstOrFail();

            if (! in_array($ticket->status, [
                QueueStatus::CREATED,
                QueueStatus::CALLED,
                QueueStatus::IN_PROGRESS,
            ], true)) {
                throw new \LogicException('Only active queue tickets can be transferred.');
            }

            if ($targetStation->id === $ticket->station_id) {
                throw new \LogicException('Transfer requires a different station.');
            }

            $currentStep = $ticket->visitWorkflowStep->workflowStep;

            if (! $currentStep->stations()->whereKey($targetStation->id)->exists()) {
                throw new \LogicException('Transfer target station is not allowed for this workflow step.');
            }

            if ($targetStation->department_id !== $ticket->visit->department_id) {
                throw new \LogicException('Transfer target must belong to the visit department.');
            }

            if (! $this->stateMachine->apply(
                $ticket,
                QueueStatus::TRANSFERRED,
                $transferredByUserId,
                ['target_station_id' => $targetStation->id],
            )) {
                throw new \LogicException('Ticket cannot transition to TRANSFERRED.');
            }

            $ticket->update([
                'transferred_to_station_id' => $targetStation->id,
            ]);

            if ($ticket->visit->status === VisitStatus::IN_PROGRESS) {
                $ticket->visit->update([
                    'status' => VisitStatus::WAITING->value,
                ]);
            }

            $allocation = (new QueueNumberGenerator)->allocate($targetStation);
            $newTicket = QueueTicket::create([
                'visit_id' => $ticket->visit_id,
                'visit_workflow_step_id' => $ticket->visit_workflow_step_id,
                'station_id' => $targetStation->id,
                'queue_number' => $allocation['queue_number'],
                'priority' => $ticket->priority->value,
                'internal_sequence' => $allocation['internal_sequence'],
                'status' => QueueStatus::CREATED->value,
                'transferred_from_ticket_id' => $ticket->id,
                'notes' => $ticket->notes,
            ]);

            $newTicket->events()->create([
                'event_type' => QueueEventType::CREATED,
                'from_status' => null,
                'to_status' => QueueStatus::CREATED->value,
                'user_id' => $transferredByUserId,
                'payload' => [
                    'visit_id' => $newTicket->visit_id,
                    'station_id' => $newTicket->station_id,
                    'transferred_from_ticket_id' => $ticket->id,
                ],
            ]);

            AuditLog::create([
                'user_id' => $transferredByUserId,
                'action' => 'QUEUE_TICKET_TRANSFER_CREATED',
                'auditable_type' => QueueTicket::class,
                'auditable_id' => $newTicket->id,
                'new_values' => [
                    'status' => QueueStatus::CREATED->value,
                    'visit_id' => $newTicket->visit_id,
                    'station_id' => $newTicket->station_id,
                    'transferred_from_ticket_id' => $ticket->id,
                ],
            ]);

            return [
                'original' => $ticket->fresh(),
                'new' => $newTicket->fresh(),
            ];
        });
    }
}
