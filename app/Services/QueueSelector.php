<?php

namespace App\Services;

use App\Enums\Priority;
use App\Enums\QueueStatus;
use App\Models\QueueTicket;
use App\Models\Station;
use Illuminate\Support\Facades\DB;

class QueueSelector
{
    public function callNext(Station $station): ?QueueTicket
    {
        return DB::transaction(function () use ($station) {
            // Lock eligible tickets for station, ordered correctly
            $ticket = QueueTicket::where('station_id', $station->id)
                ->whereIn('status', [
                    QueueStatus::CREATED->value,
                    QueueStatus::CALLED->value,
                ])
                ->orderByDesc('priority')
                ->orderBy('internal_sequence')
                ->lockForUpdate()
                ->first();

            if (! $ticket) {
                return null;
            }

            $machine = new QueueStateMachine();
            $machine->apply($ticket, QueueStatus::CALLED);

            return $ticket->fresh();
        });
    }

    public function selectForStation(Station $station, ?int $limit = 20): \Illuminate\Database\Eloquent\Collection
    {
        return QueueTicket::where('station_id', $station->id)
            ->whereIn('status', [
                QueueStatus::CREATED->value,
                QueueStatus::CALLED->value,
                QueueStatus::ON_HOLD->value,
            ])
            ->orderByDesc('priority')
            ->orderBy('internal_sequence')
            ->limit($limit)
            ->get();
    }
}
