<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class QueueAcquisitionResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        $ticket = $this->visit?->queueTickets?->sortByDesc('id')->first();

        return [
            'id' => $this->id,
            'status' => $this->status?->value,
            'channel' => $this->channel?->value,
            'acquired_at' => $this->acquired_at?->toISOString(),
            'department' => [
                'id' => $this->department?->id,
                'code' => $this->department?->code,
                'name' => $this->department?->name,
            ],
            'visit' => [
                'id' => $this->visit?->id,
                'visit_number' => $this->visit?->visit_number,
            ],
            'queue_ticket' => $ticket ? [
                'id' => $ticket->id,
                'queue_number' => $ticket->queue_number,
                'status' => $ticket->status?->value,
                'priority' => $ticket->priority?->value,
                'station_id' => $ticket->station_id,
            ] : null,
        ];
    }
}
