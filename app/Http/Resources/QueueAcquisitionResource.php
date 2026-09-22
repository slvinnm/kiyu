<?php

namespace App\Http\Resources;

use App\Enums\StationType;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class QueueAcquisitionResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        $ticket = $this->visit?->queueTickets
            ?->filter(fn ($ticket) => $ticket->station?->type === StationType::REGISTRATION)
            ->sortByDesc('id')
            ->first();

        return [
            'id' => $this->id,
            'status' => $this->status?->value,
            'channel' => $this->channel?->value,
            'acquired_at' => $this->acquired_at?->toISOString(),
            'registered_at' => $this->registered_at?->toISOString(),
            'department' => [
                'id' => $this->department?->id,
                'code' => $this->department?->code,
                'name' => $this->department?->name,
            ],
            'patient' => $this->visit?->patient ? [
                'id' => $this->visit->patient->id,
                'name' => $this->visit->patient->name,
                'medical_record_number' => $this->visit->patient->medical_record_number,
            ] : null,
            'visit' => [
                'id' => $this->visit?->id,
                'visit_number' => $this->visit?->visit_number,
                'status' => $this->visit?->status?->value,
            ],
            'queue_ticket' => $ticket ? [
                'id' => $ticket->id,
                'queue_number' => $ticket->queue_number,
                'status' => $ticket->status?->value,
                'priority' => $ticket->priority?->value,
                'station_id' => $ticket->station_id,
                'called_at' => $ticket->called_at?->toISOString(),
                'started_at' => $ticket->started_at?->toISOString(),
                'completed_at' => $ticket->completed_at?->toISOString(),
            ] : null,
        ];
    }
}
