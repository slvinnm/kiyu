<?php

namespace App\Services;

use App\Enums\Priority;
use App\Enums\QueueAcquisitionStatus;
use App\Enums\QueueEventType;
use App\Enums\QueueTicketStatus;
use App\Enums\VisitStatus;
use App\Exceptions\InvalidStateException;
use App\Models\QueueEvent;
use App\Models\QueueTicket;
use App\Models\Visit;
use Illuminate\Support\Facades\DB;

class VisitService
{
    public function __construct(
        public AuditService $auditService
    ) {}

    /**
     * Update visit priority and propagate to active tickets only.
     */
    public function updatePriority(Visit $visit, int $priority, ?string $userId = null): Visit
    {
        $newPriority = Priority::tryFrom($priority);
        if (! $newPriority) {
            throw new InvalidStateException("Invalid priority value: {$priority}. Must be 1 (Normal), 2 (Priority), or 3 (Emergency).");
        }

        return DB::transaction(function () use ($visit, $newPriority, $userId): Visit {
            $prevPriority = $visit->priority;
            $visit->update(['priority' => $newPriority]);

            // Propagate ONLY to active / pending tickets
            $activeTickets = QueueTicket::query()
                ->where('visit_id', $visit->id)
                ->whereIn('status', [QueueTicketStatus::Created, QueueTicketStatus::Called, QueueTicketStatus::OnHold])
                ->lockForUpdate()
                ->get();

            foreach ($activeTickets as $ticket) {
                $ticket->update(['priority' => $newPriority]);
            }

            $this->auditService->log(
                action: 'VISIT_PRIORITY_CHANGED',
                entityType: 'Visit',
                entityId: $visit->id,
                metadata: [
                    'from_priority' => $prevPriority->value,
                    'to_priority' => $newPriority->value,
                    'updated_ticket_count' => $activeTickets->count(),
                ],
                userId: $userId
            );

            return $visit->fresh(['patient', 'department', 'workflow', 'queueTickets']);
        });
    }

    /**
     * Cancel a visit and all its active tickets.
     */
    public function cancelVisit(Visit $visit, ?string $reason = null, ?string $userId = null): Visit
    {
        return DB::transaction(function () use ($visit, $reason, $userId): Visit {
            if ($visit->status === VisitStatus::Completed) {
                throw new InvalidStateException("Completed visit #{$visit->visit_number} cannot be cancelled.");
            }

            $visit->update(['status' => VisitStatus::Cancelled]);

            // Cancel any active tickets
            $activeTickets = QueueTicket::query()
                ->where('visit_id', $visit->id)
                ->whereIn('status', [QueueTicketStatus::Created, QueueTicketStatus::Called, QueueTicketStatus::InProgress, QueueTicketStatus::OnHold])
                ->lockForUpdate()
                ->get();

            foreach ($activeTickets as $ticket) {
                $prevStatus = $ticket->status;
                $ticket->update([
                    'status' => QueueTicketStatus::Cancelled,
                    'notes' => $reason ? ($ticket->notes ? "{$ticket->notes}. Reason: {$reason}" : "Reason: {$reason}") : $ticket->notes,
                ]);

                QueueEvent::query()->create([
                    'queue_ticket_id' => $ticket->id,
                    'event_type' => QueueEventType::Cancelled,
                    'from_status' => $prevStatus->value,
                    'to_status' => QueueTicketStatus::Cancelled->value,
                    'user_id' => $userId,
                    'payload' => ['reason' => $reason],
                ]);
            }

            // Cancel acquisition if still ACQUIRED
            if ($visit->queueAcquisition && $visit->queueAcquisition->status === QueueAcquisitionStatus::Acquired) {
                $visit->queueAcquisition->update(['status' => QueueAcquisitionStatus::Cancelled]);
            }

            $this->auditService->log(
                action: 'VISIT_CANCELLED',
                entityType: 'Visit',
                entityId: $visit->id,
                metadata: ['reason' => $reason],
                userId: $userId
            );

            return $visit->fresh(['patient', 'department', 'workflow', 'queueTickets']);
        });
    }
}
