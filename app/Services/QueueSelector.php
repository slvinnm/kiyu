<?php

namespace App\Services;

use App\Enums\QueueStatus;
use App\Models\QueueTicket;
use App\Models\Station;
use Illuminate\Support\Facades\DB;

class QueueSelector
{
    public function callNext(Station $station): ?QueueTicket
    {
        return DB::transaction(function () use ($station) {
            // ONLY select genuinely waiting CREATED tickets
            $ticket = QueueTicket::where('station_id', $station->id)
                ->where('status', QueueStatus::CREATED->value)
                ->orderByDesc('priority')
                ->orderBy('internal_sequence', 'asc')
                ->lockForUpdate()
                ->first();

            if (! $ticket) {
                return null;
            }

            // Selection ONLY — no state mutation. QueueService performs transition.
            return $ticket;
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
            ->orderBy('internal_sequence', 'asc')
            ->limit($limit)
            ->get();
    }
}