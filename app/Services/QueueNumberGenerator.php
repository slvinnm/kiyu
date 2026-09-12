<?php

namespace App\Services;

use App\Models\QueueCounter;
use App\Models\Station;
use Illuminate\Support\Facades\DB;

class QueueNumberGenerator
{
    public function generate(Station $station, ?\DateTime $date = null): string
    {
        $counterDate = $date ? $date->format('Y-m-d') : now()->format('Y-m-d');
        $prefix = $station->queue_prefix;

        // Lock the counter row to prevent concurrent collisions
        $counter = DB::transaction(function () use ($station, $counterDate, $prefix) {
            $counter = QueueCounter::lockForUpdate()
                ->firstOrCreate([
                    'station_id' => $station->id,
                    'counter_date' => $counterDate,
                ], [
                    'last_number' => 0,
                ]);

            $counter->increment('last_number');
            $counter->fresh();
            return $counter;
        });

        $number = str_pad($counter->last_number, 3, '0', STR_PAD_LEFT);
        return $prefix . '-' . $number;
    }
}
