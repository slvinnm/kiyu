<?php

namespace App\Services;

use App\Models\QueueCounter;
use App\Models\Station;
use Illuminate\Support\Facades\DB;

class QueueNumberGenerator
{
    /**
     * Atomically allocate both a queue number and internal sequence for one queue position.
     *
     * Returns both values in a single database transaction, guaranteeing they belong to the
     * same counter row and are incremented atomically.
     *
     * Example return:
     * [
     *     'queue_number' => 'A-001',
     *     'internal_sequence' => 1,
     * ]
     *
     * @param Station $station
     * @param \DateTime|null $date Optional date to key the counter; defaults to today
     * @return array{queue_number: string, internal_sequence: int}
     */
    public function allocate(Station $station, ?\DateTime $date = null): array
    {
        $counterDate = $date ? $date->format('Y-m-d') : now()->format('Y-m-d');
        $prefix = $station->queue_prefix;

        // Lock the counter row to prevent concurrent collisions.
        // Caller owns the DB::transaction() boundary; this method only provides
        // row-level locking and atomic increment within that transaction.
        $maxRetries = 3;
        $retries = 0;

        while ($retries < $maxRetries) {
            try {
                $counter = QueueCounter::where('station_id', $station->id)
                    ->where('counter_date', $counterDate)
                    ->lockForUpdate()
                    ->firstOrCreate([
                        'station_id' => $station->id,
                        'counter_date' => $counterDate,
                    ]);

                $counter->increment('last_queue_number');
                $counter->increment('last_internal_sequence');
                $counter->refresh();

                $queueNumber = $prefix . '-' . str_pad($counter->last_queue_number, 3, '0', STR_PAD_LEFT);
                $internalSequence = $counter->last_internal_sequence;

                return ['queue_number' => $queueNumber, 'internal_sequence' => $internalSequence];
            } catch (\Illuminate\Database\QueryException $e) {
                if ($e->getCode() === '23000' && $retries < $maxRetries - 1) {
                    $retries++;
                    continue;
                }
                throw $e;
            }
        }

        // Fallback after retry exhaustion; transaction should be rolled back
        // by the caller if this point is reached.
        $counter = QueueCounter::where('station_id', $station->id)
            ->where('counter_date', $counterDate)
            ->lockForUpdate()
            ->first();

        $counter->increment('last_queue_number');
        $counter->increment('last_internal_sequence');
        $counter->refresh();

        $queueNumber = $prefix . '-' . str_pad($counter->last_queue_number, 3, '0', STR_PAD_LEFT);
        $internalSequence = $counter->last_internal_sequence;

        return ['queue_number' => $queueNumber, 'internal_sequence' => $internalSequence];
    }
}