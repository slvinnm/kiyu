<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class QueueTicketResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'queue_number' => $this->queue_number,
            'status' => $this->status?->value,
            'priority' => $this->priority?->value,
            'internal_sequence' => $this->internal_sequence,
            'station' => [
                'id' => $this->station?->id,
                'code' => $this->station?->code,
                'name' => $this->station?->name,
                'type' => $this->station?->type?->value,
            ],
            'visit' => [
                'id' => $this->visit?->id,
                'visit_number' => $this->visit?->visit_number,
                'status' => $this->visit?->status?->value,
            ],
            'workflow_step' => [
                'id' => $this->visitWorkflowStep?->workflowStep?->id,
                'name' => $this->visitWorkflowStep?->workflowStep?->name,
                'sequence' => $this->visitWorkflowStep?->workflowStep?->sequence,
            ],
            'called_at' => $this->called_at?->toISOString(),
            'started_at' => $this->started_at?->toISOString(),
            'completed_at' => $this->completed_at?->toISOString(),
        ];
    }
}
