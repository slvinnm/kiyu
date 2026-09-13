<?php

namespace App\Services;

use App\Enums\QueueStatus;
use App\Models\Patient;
use App\Models\QueueTicket;
use Illuminate\Database\Eloquent\Collection;

class PatientQueueService
{
    public function activeTickets(Patient $patient): Collection
    {
        $tickets = QueueTicket::query()
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

        $tickets->each(function (QueueTicket $ticket): void {
            $ticket->queue_position = $this->queuePosition($ticket);
        });

        return $tickets;
    }

    private function queuePosition(QueueTicket $ticket): ?int
    {
        if ($ticket->status !== QueueStatus::CREATED) {
            return null;
        }

        $queueDate = $ticket->created_at?->toDateString();

        if (! $queueDate) {
            return null;
        }

        return QueueTicket::query()
            ->where('station_id', $ticket->station_id)
            ->whereDate('created_at', $queueDate)
            ->where('status', QueueStatus::CREATED->value)
            ->where(function ($query) use ($ticket) {
                $query
                    ->where('priority', '>', $ticket->priority->value)
                    ->orWhere(function ($query) use ($ticket) {
                        $query
                            ->where('priority', $ticket->priority->value)
                            ->where('internal_sequence', '<', $ticket->internal_sequence);
                    });
            })
            ->count() + 1;
    }
}
