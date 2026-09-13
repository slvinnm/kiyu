<?php

namespace App\Services;

use App\Enums\QueueStatus;
use App\Enums\VisitStatus;
use App\Models\Patient;
use App\Models\QueueTicket;
use Illuminate\Database\Eloquent\Collection;

class PatientQueueService
{
    public function activeTickets(Patient $patient): Collection
    {
        $tickets = QueueTicket::query()
            ->select('queue_tickets.*')
            ->selectRaw(
                <<<'SQL'
                CASE WHEN queue_tickets.status = ? THEN (
                    SELECT COUNT(*) + 1
                    FROM queue_tickets AS ahead
                    INNER JOIN visits AS ahead_visits ON ahead_visits.id = ahead.visit_id
                    WHERE ahead.station_id = queue_tickets.station_id
                      AND ahead.created_at >= DATE(queue_tickets.created_at)
                      AND ahead.created_at < DATE_ADD(DATE(queue_tickets.created_at), INTERVAL 1 DAY)
                      AND ahead.status = ?
                      AND ahead_visits.status = ?
                      AND (
                          ahead.priority > queue_tickets.priority
                          OR (
                              ahead.priority = queue_tickets.priority
                              AND ahead.internal_sequence < queue_tickets.internal_sequence
                          )
                      )
                ) ELSE NULL END AS queue_position
                SQL,
                [
                    QueueStatus::CREATED->value,
                    QueueStatus::CREATED->value,
                    VisitStatus::WAITING->value,
                ],
            )
            ->whereHas('visit', fn ($query) => $query->where('patient_id', $patient->id))
            ->whereIn('status', [
                QueueStatus::CREATED->value,
                QueueStatus::CALLED->value,
                QueueStatus::IN_PROGRESS->value,
                QueueStatus::ON_HOLD->value,
            ])
            ->with([
                'station.department',
                'visit',
                'visitWorkflowStep.workflowStep',
            ])
            ->orderBy('visit_id')
            ->orderBy('id')
            ->get();

        return $tickets;
    }
}
