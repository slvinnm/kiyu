<?php

namespace App\Services;

use App\Enums\QueueStatus;
use App\Models\QueueTicket;
use App\Models\Station;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

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
            ->first();

        if (! $station) {
            throw ValidationException::withMessages([
                'station' => 'The requested station is not available.',
            ]);
        }

        $today = Carbon::today();

        $tickets = QueueTicket::query()
            ->where('station_id', $station->id)
            ->whereDate('created_at', $today)
            ->with(['station.department'])
            ->get();

        $current = $tickets
            ->whereIn('status', [
                QueueStatus::IN_PROGRESS->value,
                QueueStatus::CALLED->value,
            ])
            ->sortByDesc(fn (QueueTicket $ticket): int => $ticket->started_at?->getTimestamp() ?? $ticket->called_at?->getTimestamp() ?? 0)
            ->first();

        $upcoming = $tickets
            ->where('status', QueueStatus::CREATED->value)
            ->sort(function (QueueTicket $left, QueueTicket $right): int {
                if ($left->priority->value !== $right->priority->value) {
                    return $right->priority->value <=> $left->priority->value;
                }

                return $left->internal_sequence <=> $right->internal_sequence;
            })
            ->take($upcomingLimit)
            ->values();

        return [
            'station' => $station,
            'current' => $current,
            'upcoming' => $upcoming,
        ];
    }
}
