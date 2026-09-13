<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class VisitResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'visit_number' => $this->visit_number,
            'status' => $this->status?->value,
            'priority' => $this->priority?->value,
            'intake_channel' => $this->intake_channel?->value,
            'department' => [
                'id' => $this->department?->id,
                'code' => $this->department?->code,
                'name' => $this->department?->name,
            ],
            'queue_tickets' => QueueTicketResource::collection($this->whenLoaded('queueTickets')),
            'checked_in_at' => $this->checked_in_at?->toISOString(),
            'completed_at' => $this->completed_at?->toISOString(),
        ];
    }
}
