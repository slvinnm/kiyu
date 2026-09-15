<?php

namespace App\Services;

use App\Models\QueueCounter;
use App\Models\Station;

class QueueNumberGenerator
{
    /**
     * Allocate both a queue number and internal sequence for one queue position.
     *
     * The caller owns the DB transaction boundary.
     *
     * This method MUST be called inside an active database transaction.
     * The QueueCounter row is locked with lockForUpdate() so concurrent
     * allocations for the same station/date are serialized.
     *
     * @param  \DateTime|null  $date  Optional date to key the counter; defaults to today
     * @return array{queue_number: string, internal_sequence: int}
     */
    public function allocate(Station $station, ?\DateTime $date = null): array
    {
        $counterDate = $date ? $date->format('Y-m-d') : now()->format('Y-m-d');
        $prefix = $station->queue_prefix;

        /*
         * Ensure the counter row exists without throwing a duplicate-key
         * exception under concurrent first-time allocation.
         *
         * The unique constraint on (station_id, counter_date) is the final
         * database-level guarantee.
         */
        QueueCounter::query()->insertOrIgnore([
            'station_id' => $station->id,
            'counter_date' => $counterDate,
            'last_queue_number' => 0,
            'last_internal_sequence' => 0,
        ]);

        /*
         * The row now exists. Lock it for the remainder of the caller's
         * transaction so concurrent allocations for the same station/date
         * are serialized.
         */
        $counter = QueueCounter::query()
            ->where('station_id', $station->id)
            ->where('counter_date', $counterDate)
            ->lockForUpdate()
            ->firstOrFail();

        $counter->increment('last_queue_number');
        $counter->increment('last_internal_sequence');
        $counter->refresh();

        $queueNumber = $prefix . '-'
            . str_pad($counter->last_queue_number, 3, '0', STR_PAD_LEFT);

        return [
            'queue_number' => $queueNumber,
            'internal_sequence' => $counter->last_internal_sequence,
        ];
    }
}
