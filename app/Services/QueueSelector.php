<?php

namespace App\Services;

use App\Enums\QueueStatus;
use App\Enums\VisitStatus;
use App\Models\QueueTicket;
use App\Models\Station;
use Illuminate\Database\Eloquent\Collection;

class QueueSelector
{
    public function callNext(Station $station): ?QueueTicket
    {
        // Selection ONLY — no state mutation. QueueService owns the transaction
        // and performs the CREATED → CALLED transition while the lock is held.
        $ticket = QueueTicket::where('station_id', $station->id)
            ->where('status', QueueStatus::CREATED->value)
            ->whereHas('visit', function ($query): void {
                $query->where('status', VisitStatus::WAITING->value);
            })
            ->orderByDesc('priority')
            ->orderBy('internal_sequence', 'asc')
            ->lockForUpdate()
            ->first();

        return $ticket;
    }

    public function selectForStation(Station $station, ?int $limit = 20): Collection
    {
        return QueueTicket::where('station_id', $station->id)
            ->whereHas('visit', function ($query): void {
                $query->where('status', VisitStatus::WAITING->value);
            })
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
