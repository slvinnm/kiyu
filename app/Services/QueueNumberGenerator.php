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

        $number = DB::transaction(function () use ($station, $counterDate, $prefix) {
            // Lock the counter row to prevent concurrent collisions
            $counter = QueueCounter::where('station_id', $station->id)
                ->where('counter_date', $counterDate)
                ->firstOrCreate([
                    'station_id' => $station->id,
                    'counter_date' => $counterDate,
                ]);

            $counter->increment('last_number');
            $counter->refresh();

            $number = str_pad($counter->last_number, 3, '0', STR_PAD_LEFT);
            return $prefix . '-' . $number;
        });

        return $number;
    }

    /**
     * Get the next internal sequence number for a station.
     * This is used for FIFO ordering within the same priority.
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
            $counter = QueueCounter::where('station_id', $station->id)
                ->where('counter_date', $counterDate)
                ->firstOrCreate([
                    'station_id' => $station->id,
                    'counter_date' => $counterDate,
                ]);

            $counter->increment('last_number');
            $counter->refresh();

            return $counter->last_number;
        });
    }
}