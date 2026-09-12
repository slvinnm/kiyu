<?php

namespace App\Services;

use App\Models\QueueCounter;
use App\Models\Station;
use Illuminate\Support\Facades\DB;

class QueueNumberGenerator
{
    /**
     * Generate a human-readable queue number for a station.
     *
     * Examples: A-001, T-018, C-011, L-001, R-001
     *
     * The method uses a date-scoped counter with row-level locking
     * to prevent collisions under concurrent requests.
     *
     * @param Station $station
     * @param \DateTime|null $date Optional date to key the counter; defaults to today
     * @return string
     */
    public function generate(Station $station, ?\DateTime $date = null): string
    {
        $counterDate = $date ? $date->format('Y-m-d') : now()->format('Y-m-d');
        $prefix = $station->queue_prefix;

        return DB::transaction(function () use ($station, $counterDate, $prefix) {
            // Lock the counter row to prevent concurrent collisions
            // firstOrCreate with unique constraint: if row exists, we get it;
            // if not, we create it. A race may cause a unique constraint violation
            // that the transaction will catch and retry.
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
                    $counter->refresh();

                    $number = str_pad($counter->last_queue_number, 3, '0', STR_PAD_LEFT);
                    return $prefix . '-' . $number;
                } catch (\Illuminate\Database\QueryException $e) {
                    if ($e->getCode() === '23000' && $retries < $maxRetries - 1) {
                        $retries++;
                        continue;
                    }
                    throw $e;
                }
            }

            // Should not reach here, but fallback
            $counter = QueueCounter::where('station_id', $station->id)
                ->where('counter_date', $counterDate)
                ->lockForUpdate()
                ->first();

            $counter->increment('last_queue_number');
            $counter->refresh();

            $number = str_pad($counter->last_queue_number, 3, '0', STR_PAD_LEFT);
            return $prefix . '-' . $number;
        });
    }

    /**
     * Get the next internal sequence number for a station.
     * This is used for FIFO ordering within the same priority.
     * Separate counter from queue number generation.
     *
     * @param Station $station
     * @param \DateTime|null $date Optional date to key the counter; defaults to today
     * @return int
     */
    public function getSequence(Station $station, ?\DateTime $date = null): int
    {
        $counterDate = $date ? $date->format('Y-m-d') : now()->format('Y-m-d');

        return DB::transaction(function () use ($station, $counterDate) {
            // Lock the counter row to prevent concurrent collisions
            // Same unique constraint as queue number generation
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

                    $counter->increment('last_internal_sequence');
                    $counter->refresh();

                    return $counter->last_internal_sequence;
                } catch (\Illuminate\Database\QueryException $e) {
                    if ($e->getCode() === '23000' && $retries < $maxRetries - 1) {
                        $retries++;
                        continue;
                    }
                    throw $e;
                }
            }

            // Fallback
            $counter = QueueCounter::where('station_id', $station->id)
                ->where('counter_date', $counterDate)
                ->lockForUpdate()
                ->first();

            $counter->increment('last_internal_sequence');
            $counter->refresh();

            return $counter->last_internal_sequence;
        });
    }
}