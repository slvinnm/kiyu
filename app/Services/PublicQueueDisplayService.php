<?php

namespace App\Services;

use App\Enums\QueueStatus;
use App\Enums\VisitStatus;
use App\Models\QueueTicket;
use App\Models\Station;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Support\Carbon;

class PublicQueueDisplayService
{
    /**
     * @return array{station: Station, current: QueueTicket|null, upcoming: Collection<int, QueueTicket>}
     */
    public function forStation(string $stationCode, int $upcomingLimit = 5): array
    {
        $station = Station::query()
            ->where('code', $stationCode)
            ->where('is_active', true)
            ->with('department')
            ->firstOrFail();

        $today = Carbon::today();

        $current = QueueTicket::query()
            ->where('station_id', $station->id)
            ->whereDate('created_at', $today)
            ->whereIn('status', [
                QueueStatus::IN_PROGRESS->value,
                QueueStatus::CALLED->value,
            ])
            ->with(['station', 'visit', 'visitWorkflowStep.workflowStep'])
            ->orderByRaw('COALESCE(started_at, called_at, created_at) DESC')
            ->orderByDesc('id')
            ->first();

        $upcoming = QueueTicket::query()
            ->where('station_id', $station->id)
            ->whereDate('created_at', $today)
            ->where('status', QueueStatus::CREATED->value)
            ->whereHas('visit', fn ($query) => $query->where('status', VisitStatus::WAITING->value))
            ->with(['station', 'visit', 'visitWorkflowStep.workflowStep'])
            ->orderByDesc('priority')
            ->orderBy('internal_sequence')
            ->limit(max(1, min($upcomingLimit, 10)))
            ->get();

        return [
            'station' => $station,
            'current' => $current,
            'upcoming' => $upcoming,
        ];
    }
}
