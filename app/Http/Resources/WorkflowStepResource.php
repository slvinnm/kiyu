<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class WorkflowStepResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'name' => $this->name,
            'sequence' => $this->sequence,
            'requires_queue' => $this->requires_queue,
            'is_optional' => $this->is_optional,
            'is_repeatable' => $this->is_repeatable,
            'can_skip' => $this->can_skip,
        ];
    }
}
