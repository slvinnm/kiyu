<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class ReferralResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'status' => $this->status?->value,
            'priority' => $this->priority?->value,
            'reason' => $this->reason,
            'source_visit' => [
                'id' => $this->sourceVisit?->id,
                'visit_number' => $this->sourceVisit?->visit_number,
                'department' => [
                    'id' => $this->sourceVisit?->department?->id,
                    'code' => $this->sourceVisit?->department?->code,
                    'name' => $this->sourceVisit?->department?->name,
                ],
            ],
            'target_department' => [
                'id' => $this->targetDepartment?->id,
                'code' => $this->targetDepartment?->code,
                'name' => $this->targetDepartment?->name,
            ],
            'target_visit' => [
                'id' => $this->targetVisit?->id,
                'visit_number' => $this->targetVisit?->visit_number,
                'status' => $this->targetVisit?->status?->value,
                'queue_tickets' => QueueTicketResource::collection(
                    $this->whenLoaded('targetVisit')->targetVisit?->queueTickets ?? collect(),
                ),
            ],
            'referred_by_user_id' => $this->referred_by_user_id,
            'created_at' => $this->created_at?->toISOString(),
        ];
    }
}
