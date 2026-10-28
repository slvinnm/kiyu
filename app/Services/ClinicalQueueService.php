<?php

namespace App\Services;

use App\Enums\QueueEventType;
use App\Enums\QueueTicketStatus;
use App\Enums\VisitStatus;
use App\Exceptions\InvalidStateException;
use App\Models\QueueEvent;
use App\Models\QueueTicket;
use App\Models\Station;
use App\Models\Visit;
use Illuminate\Support\Facades\DB;

class ClinicalQueueService
{
    public function __construct(
        public SequenceService $sequenceService,
        public WorkflowService $workflowService,
        public AuditService $auditService
    ) {}

    /**
     * Call the next eligible ticket for a clinical station.
     */
    public function callNext(int $stationId, ?string $assignedTo = null): ?QueueTicket
    {
        $station = Station::query()->findOrFail($stationId);
        if (! $station->is_active) {
            throw new InvalidStateException("Station #{$stationId} is inactive.");
        }

        return DB::transaction(function () use ($station, $assignedTo): ?QueueTicket {
            /** @var QueueTicket|null $ticket */
            $ticket = QueueTicket::query()
                ->where('station_id', $station->id)
                ->where('status', QueueTicketStatus::Created)
                ->orderBy('priority', 'desc')
                ->orderBy('internal_sequence', 'asc')
                ->lockForUpdate()
                ->first();

            if (! $ticket) {
                return null;
            }

            $ticket->update([
                'status' => QueueTicketStatus::Called,
                'called_at' => now(),
                'assigned_to' => $assignedTo,
            ]);

            QueueEvent::query()->create([
                'queue_ticket_id' => $ticket->id,
                'event_type' => QueueEventType::Called,
                'from_status' => QueueTicketStatus::Created->value,
                'to_status' => QueueTicketStatus::Called->value,
                'user_id' => $assignedTo,
            ]);

            $this->auditService->log(
                action: 'CLINICAL_CALL_NEXT',
                entityType: 'QueueTicket',
                entityId: $ticket->id,
                metadata: [
                    'queue_number' => $ticket->queue_number,
                    'station_id' => $station->id,
                    'assigned_to' => $assignedTo,
                ],
                userId: $assignedTo
            );

            return $ticket->load(['visit.patient', 'station', 'workflowStep']);
        });
    }

    /**
     * Recall a previously called clinical ticket (sound the announcement again).
     */
    public function recall(QueueTicket $ticket, ?string $userId = null): QueueTicket
    {
        return DB::transaction(function () use ($ticket, $userId): QueueTicket {
            /** @var QueueTicket $lockedTicket */
            $lockedTicket = QueueTicket::query()->lockForUpdate()->findOrFail($ticket->id);

            if ($lockedTicket->status !== QueueTicketStatus::Called) {
                throw new InvalidStateException("Ticket #{$lockedTicket->queue_number} cannot be recalled from status '{$lockedTicket->status->value}'. Must be CALLED.");
            }

            QueueEvent::query()->create([
                'queue_ticket_id' => $lockedTicket->id,
                'event_type' => QueueEventType::Recalled,
                'from_status' => QueueTicketStatus::Called->value,
                'to_status' => QueueTicketStatus::Called->value,
                'user_id' => $userId,
                'payload' => ['reason' => 'Clinical recall announced'],
            ]);

            $this->auditService->log(
                action: 'CLINICAL_RECALL',
                entityType: 'QueueTicket',
                entityId: $lockedTicket->id,
                metadata: [
                    'queue_number' => $lockedTicket->queue_number,
                    'station_id' => $lockedTicket->station_id,
                ],
                userId: $userId
            );

            return $lockedTicket->load(['visit.patient', 'station', 'workflowStep']);
        });
    }

    /**
     * Start clinical service for a ticket.
     */
    public function start(QueueTicket $ticket, ?string $userId = null): QueueTicket
    {
        return DB::transaction(function () use ($ticket, $userId): QueueTicket {
            /** @var QueueTicket $lockedTicket */
            $lockedTicket = QueueTicket::query()->with('visit')->lockForUpdate()->findOrFail($ticket->id);

            if ($lockedTicket->status !== QueueTicketStatus::Called) {
                throw new InvalidStateException("Ticket #{$lockedTicket->queue_number} cannot start from status '{$lockedTicket->status->value}'. Must be CALLED.");
            }

            $prevStatus = $lockedTicket->status;
            $lockedTicket->update([
                'status' => QueueTicketStatus::InProgress,
                'started_at' => now(),
            ]);

            QueueEvent::query()->create([
                'queue_ticket_id' => $lockedTicket->id,
                'event_type' => QueueEventType::Started,
                'from_status' => $prevStatus->value,
                'to_status' => QueueTicketStatus::InProgress->value,
                'user_id' => $userId,
            ]);

            // Transition Visit to IN_PROGRESS when clinical service starts
            /** @var Visit $visit */
            $visit = $lockedTicket->visit;
            if ($visit->status === VisitStatus::Waiting) {
                $visit->update([
                    'status' => VisitStatus::InProgress,
                ]);
            }

            $this->auditService->log(
                action: 'CLINICAL_TICKET_STARTED',
                entityType: 'QueueTicket',
                entityId: $lockedTicket->id,
                metadata: [
                    'queue_number' => $lockedTicket->queue_number,
                    'visit_id' => $visit->id,
                ],
                userId: $userId
            );

            return $lockedTicket->fresh(['visit.patient', 'station', 'workflowStep']);
        });
    }

    /**
     * Hold a ticket.
     */
    public function hold(QueueTicket $ticket, ?string $notes = null, ?string $userId = null): QueueTicket
    {
        return DB::transaction(function () use ($ticket, $notes, $userId): QueueTicket {
            /** @var QueueTicket $lockedTicket */
            $lockedTicket = QueueTicket::query()->lockForUpdate()->findOrFail($ticket->id);

            if (! in_array($lockedTicket->status, [QueueTicketStatus::Called, QueueTicketStatus::InProgress], true)) {
                throw new InvalidStateException("Ticket #{$lockedTicket->queue_number} cannot be held from status '{$lockedTicket->status->value}'.");
            }

            $prevStatus = $lockedTicket->status;
            $lockedTicket->update([
                'status' => QueueTicketStatus::OnHold,
                'notes' => $notes ?? $lockedTicket->notes,
            ]);

            QueueEvent::query()->create([
                'queue_ticket_id' => $lockedTicket->id,
                'event_type' => QueueEventType::Held,
                'from_status' => $prevStatus->value,
                'to_status' => QueueTicketStatus::OnHold->value,
                'user_id' => $userId,
                'payload' => ['notes' => $notes],
            ]);

            $this->auditService->log(
                action: 'CLINICAL_TICKET_HELD',
                entityType: 'QueueTicket',
                entityId: $lockedTicket->id,
                metadata: ['queue_number' => $lockedTicket->queue_number],
                userId: $userId
            );

            return $lockedTicket->fresh(['visit.patient', 'station', 'workflowStep']);
        });
    }

    /**
     * Resume a ticket from ON_HOLD back to CALLED.
     */
    public function resume(QueueTicket $ticket, ?string $userId = null): QueueTicket
    {
        return DB::transaction(function () use ($ticket, $userId): QueueTicket {
            /** @var QueueTicket $lockedTicket */
            $lockedTicket = QueueTicket::query()->lockForUpdate()->findOrFail($ticket->id);

            if ($lockedTicket->status !== QueueTicketStatus::OnHold) {
                throw new InvalidStateException("Ticket #{$lockedTicket->queue_number} is not ON_HOLD.");
            }

            $lockedTicket->update([
                'status' => QueueTicketStatus::Called,
                'called_at' => now(),
            ]);

            QueueEvent::query()->create([
                'queue_ticket_id' => $lockedTicket->id,
                'event_type' => QueueEventType::Resumed,
                'from_status' => QueueTicketStatus::OnHold->value,
                'to_status' => QueueTicketStatus::Called->value,
                'user_id' => $userId,
            ]);

            $this->auditService->log(
                action: 'CLINICAL_TICKET_RESUMED',
                entityType: 'QueueTicket',
                entityId: $lockedTicket->id,
                metadata: ['queue_number' => $lockedTicket->queue_number],
                userId: $userId
            );

            return $lockedTicket->fresh(['visit.patient', 'station', 'workflowStep']);
        });
    }

    /**
     * Complete a ticket and advance the workflow.
     *
     * @return array{ticket: QueueTicket, next_ticket: ?QueueTicket, visit: Visit}
     */
    public function complete(QueueTicket $ticket, ?string $notes = null, ?string $userId = null): array
    {
        return DB::transaction(function () use ($ticket, $notes, $userId): array {
            /** @var QueueTicket $lockedTicket */
            $lockedTicket = QueueTicket::query()
                ->with(['visit.workflow', 'workflowStep'])
                ->lockForUpdate()
                ->findOrFail($ticket->id);

            if ($lockedTicket->status !== QueueTicketStatus::InProgress) {
                throw new InvalidStateException("Ticket #{$lockedTicket->queue_number} cannot be completed from status '{$lockedTicket->status->value}'. Must be IN_PROGRESS.");
            }

            $lockedTicket->update([
                'status' => QueueTicketStatus::Completed,
                'completed_at' => now(),
                'notes' => $notes ?? $lockedTicket->notes,
            ]);

            QueueEvent::query()->create([
                'queue_ticket_id' => $lockedTicket->id,
                'event_type' => QueueEventType::Completed,
                'from_status' => QueueTicketStatus::InProgress->value,
                'to_status' => QueueTicketStatus::Completed->value,
                'user_id' => $userId,
                'payload' => ['notes' => $notes],
            ]);

            /** @var Visit $visit */
            $visit = $lockedTicket->visit;
            $workflow = $visit->workflow;
            $nextStep = $this->workflowService->resolveNextStep($workflow, $lockedTicket->workflowStep->sequence);

            $nextTicket = null;
            if ($nextStep) {
                $nextStation = $nextStep->station;
                $numbering = $this->sequenceService->generateQueueNumber($nextStation);

                /** @var QueueTicket $nextTicket */
                $nextTicket = QueueTicket::query()->create([
                    'visit_id' => $visit->id,
                    'workflow_step_id' => $nextStep->id,
                    'station_id' => $nextStation->id,
                    'queue_number' => $numbering['queue_number'],
                    'priority' => $visit->priority,
                    'internal_sequence' => $numbering['internal_sequence'],
                    'status' => QueueTicketStatus::Created,
                ]);

                QueueEvent::query()->create([
                    'queue_ticket_id' => $nextTicket->id,
                    'event_type' => QueueEventType::Created,
                    'from_status' => null,
                    'to_status' => QueueTicketStatus::Created->value,
                    'user_id' => $userId,
                    'payload' => ['reason' => 'Advancement to next workflow step'],
                ]);
            } else {
                // All steps completed -> visit COMPLETED
                $visit->update([
                    'status' => VisitStatus::Completed,
                    'completed_at' => now(),
                ]);
            }

            $this->auditService->log(
                action: 'CLINICAL_TICKET_COMPLETED',
                entityType: 'QueueTicket',
                entityId: $lockedTicket->id,
                metadata: [
                    'queue_number' => $lockedTicket->queue_number,
                    'visit_id' => $visit->id,
                    'next_ticket_id' => $nextTicket?->id,
                ],
                userId: $userId
            );

            return [
                'ticket' => $lockedTicket->fresh(['station', 'workflowStep']),
                'next_ticket' => $nextTicket?->load(['station', 'workflowStep']),
                'visit' => $visit->fresh(['patient', 'department', 'workflow']),
            ];
        });
    }

    /**
     * Skip a ticket.
     */
    public function skip(QueueTicket $ticket, ?string $notes = null, ?string $userId = null): QueueTicket
    {
        return DB::transaction(function () use ($ticket, $notes, $userId): QueueTicket {
            /** @var QueueTicket $lockedTicket */
            $lockedTicket = QueueTicket::query()->lockForUpdate()->findOrFail($ticket->id);

            if (! in_array($lockedTicket->status, [QueueTicketStatus::Called, QueueTicketStatus::InProgress], true)) {
                throw new InvalidStateException("Ticket cannot be skipped from status '{$lockedTicket->status->value}'.");
            }

            $prevStatus = $lockedTicket->status;
            $lockedTicket->update([
                'status' => QueueTicketStatus::Skipped,
                'notes' => $notes ?? $lockedTicket->notes,
            ]);

            QueueEvent::query()->create([
                'queue_ticket_id' => $lockedTicket->id,
                'event_type' => QueueEventType::Skipped,
                'from_status' => $prevStatus->value,
                'to_status' => QueueTicketStatus::Skipped->value,
                'user_id' => $userId,
                'payload' => ['notes' => $notes],
            ]);

            return $lockedTicket->fresh(['station', 'workflowStep']);
        });
    }

    /**
     * Mark a ticket as No-Show.
     */
    public function noShow(QueueTicket $ticket, ?string $notes = null, ?string $userId = null): QueueTicket
    {
        return DB::transaction(function () use ($ticket, $notes, $userId): QueueTicket {
            /** @var QueueTicket $lockedTicket */
            $lockedTicket = QueueTicket::query()->lockForUpdate()->findOrFail($ticket->id);

            if (! in_array($lockedTicket->status, [QueueTicketStatus::Called, QueueTicketStatus::InProgress], true)) {
                throw new InvalidStateException("Ticket cannot be marked no-show from status '{$lockedTicket->status->value}'.");
            }

            $prevStatus = $lockedTicket->status;
            $lockedTicket->update([
                'status' => QueueTicketStatus::NoShow,
                'notes' => $notes ?? $lockedTicket->notes,
            ]);

            QueueEvent::query()->create([
                'queue_ticket_id' => $lockedTicket->id,
                'event_type' => QueueEventType::NoShow,
                'from_status' => $prevStatus->value,
                'to_status' => QueueTicketStatus::NoShow->value,
                'user_id' => $userId,
                'payload' => ['notes' => $notes],
            ]);

            return $lockedTicket->fresh(['station', 'workflowStep']);
        });
    }

    /**
     * Cancel a ticket.
     */
    public function cancelTicket(QueueTicket $ticket, ?string $notes = null, ?string $userId = null): QueueTicket
    {
        return DB::transaction(function () use ($ticket, $notes, $userId): QueueTicket {
            /** @var QueueTicket $lockedTicket */
            $lockedTicket = QueueTicket::query()->lockForUpdate()->findOrFail($ticket->id);

            if ($lockedTicket->status->isHistorical()) {
                throw new InvalidStateException('Historical ticket cannot be cancelled.');
            }

            $prevStatus = $lockedTicket->status;
            $lockedTicket->update([
                'status' => QueueTicketStatus::Cancelled,
                'notes' => $notes ?? $lockedTicket->notes,
            ]);

            QueueEvent::query()->create([
                'queue_ticket_id' => $lockedTicket->id,
                'event_type' => QueueEventType::Cancelled,
                'from_status' => $prevStatus->value,
                'to_status' => QueueTicketStatus::Cancelled->value,
                'user_id' => $userId,
                'payload' => ['notes' => $notes],
            ]);

            return $lockedTicket->fresh(['station', 'workflowStep']);
        });
    }

    /**
     * Transfer a ticket to another compatible station in the same department and workflow step.
     *
     * @return array{old_ticket: QueueTicket, new_ticket: QueueTicket}
     */
    public function transfer(QueueTicket $ticket, int $targetStationId, ?string $notes = null, ?string $userId = null): array
    {
        return DB::transaction(function () use ($ticket, $targetStationId, $notes, $userId): array {
            /** @var QueueTicket $lockedTicket */
            $lockedTicket = QueueTicket::query()
                ->with(['station', 'workflowStep.station'])
                ->lockForUpdate()
                ->findOrFail($ticket->id);

            if (! in_array($lockedTicket->status, [QueueTicketStatus::Called, QueueTicketStatus::InProgress], true)) {
                throw new InvalidStateException("Ticket #{$lockedTicket->queue_number} cannot be transferred from status '{$lockedTicket->status->value}'. Must be CALLED or IN_PROGRESS.");
            }

            if ($lockedTicket->station_id === $targetStationId) {
                throw new InvalidStateException('Target station cannot be the same as current station.');
            }

            $targetStation = Station::query()->findOrFail($targetStationId);
            if (! $targetStation->is_active) {
                throw new InvalidStateException("Target station #{$targetStationId} is inactive.");
            }

            if ($targetStation->department_id !== $lockedTicket->station->department_id) {
                throw new InvalidStateException('Target station must belong to the same department as current station.');
            }

            $declaredType = $lockedTicket->workflowStep->station->type;
            if ($targetStation->type !== $declaredType) {
                throw new InvalidStateException("Target station type '{$targetStation->type->value}' is incompatible with step requirement '{$declaredType->value}'.");
            }

            $prevStatus = $lockedTicket->status;
            $lockedTicket->update([
                'status' => QueueTicketStatus::Transferred,
                'transferred_to_station_id' => $targetStationId,
                'notes' => $notes ?? $lockedTicket->notes,
            ]);

            QueueEvent::query()->create([
                'queue_ticket_id' => $lockedTicket->id,
                'event_type' => QueueEventType::Transferred,
                'from_status' => $prevStatus->value,
                'to_status' => QueueTicketStatus::Transferred->value,
                'user_id' => $userId,
                'payload' => [
                    'transferred_to_station_id' => $targetStationId,
                    'notes' => $notes,
                ],
            ]);

            $numbering = $this->sequenceService->generateQueueNumber($targetStation);

            /** @var QueueTicket $newTicket */
            $newTicket = QueueTicket::query()->create([
                'visit_id' => $lockedTicket->visit_id,
                'workflow_step_id' => $lockedTicket->workflow_step_id,
                'station_id' => $targetStationId,
                'queue_number' => $numbering['queue_number'],
                'priority' => $lockedTicket->priority,
                'internal_sequence' => $numbering['internal_sequence'],
                'status' => QueueTicketStatus::Created,
                'transferred_from_ticket_id' => $lockedTicket->id,
            ]);

            QueueEvent::query()->create([
                'queue_ticket_id' => $newTicket->id,
                'event_type' => QueueEventType::Created,
                'from_status' => null,
                'to_status' => QueueTicketStatus::Created->value,
                'user_id' => $userId,
                'payload' => [
                    'transferred_from_ticket_id' => $lockedTicket->id,
                    'reason' => 'Transfer from station #' . $lockedTicket->station_id,
                ],
            ]);

            $this->auditService->log(
                action: 'TICKET_TRANSFERRED',
                entityType: 'QueueTicket',
                entityId: $lockedTicket->id,
                metadata: [
                    'old_ticket_id' => $lockedTicket->id,
                    'new_ticket_id' => $newTicket->id,
                    'target_station_id' => $targetStationId,
                ],
                userId: $userId
            );

            return [
                'old_ticket' => $lockedTicket->fresh(['station', 'workflowStep']),
                'new_ticket' => $newTicket->load(['station', 'workflowStep']),
            ];
        });
    }
}
