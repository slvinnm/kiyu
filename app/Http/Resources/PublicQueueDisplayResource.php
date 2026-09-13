<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class PublicQueueDisplayResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'station' => [
                'code' => $this['station']->code,
                'name' => $this['station']->name,
                'type' => $this['station']->type?->value,
            ],
            'department' => [
                'code' => $this['station']->department?->code,
                'name' => $this['station']->department?->name,
            ],
            'current' => $this['current'] ? [
                'queue_number' => $this['current']->queue_number,
                'status' => $this['current']->status?->value,
                'called_at' => $this['current']->called_at?->toISOString(),
                'started_at' => $this['current']->started_at?->toISOString(),
            ] : null,
            'upcoming' => $this['upcoming']->map(fn ($ticket): array => [
                'queue_number' => $ticket->queue_number,
                'status' => $ticket->status?->value,
            ])->values()->all(),
        ];
    }
}
