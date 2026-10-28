<?php

namespace App\Services;

use App\Models\QueueCounter;
use App\Models\Station;
use App\Models\VisitCounter;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;

class SequenceService
{
    /**
     * Generate the next unique visit number for today.
     */
    public function generateVisitNumber(): string
    {
        $today = Carbon::today()->format('Y-m-d');

        return DB::transaction(function () use ($today): string {
            /** @var VisitCounter $counter */
            $counter = VisitCounter::query()
                ->where('counter_date', $today)
                ->lockForUpdate()
                ->first();

            if (! $counter) {
                $counter = VisitCounter::query()->create([
                    'counter_date' => $today,
                    'last_sequence' => 0,
                ]);
            }

            $counter->last_sequence += 1;
            $counter->save();

            return sprintf('V-%s-%04d', Carbon::today()->format('Ymd'), $counter->last_sequence);
        });
    }

    /**
     * Generate the next queue number and internal sequence for a station today.
     *
     * @return array{queue_number: string, internal_sequence: int}
     */
    public function generateQueueNumber(Station $station): array
    {
        $today = Carbon::today()->format('Y-m-d');

        return DB::transaction(function () use ($station, $today): array {
            /** @var QueueCounter $counter */
            $counter = QueueCounter::query()
                ->where('station_id', $station->id)
                ->where('counter_date', $today)
                ->lockForUpdate()
                ->first();

            if (! $counter) {
                $counter = QueueCounter::query()->create([
                    'station_id' => $station->id,
                    'counter_date' => $today,
                    'last_sequence' => 0,
                ]);
            }

            $counter->last_sequence += 1;
            $counter->save();

            $seq = $counter->last_sequence;
            $prefix = $station->queue_prefix ?: 'A';
            $padded = str_pad((string) $seq, 3, '0', STR_PAD_LEFT);

            return [
                'queue_number' => $prefix . $padded,
                'internal_sequence' => $seq,
            ];
        });
    }
}
