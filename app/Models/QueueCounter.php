<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Facades\DB;

class QueueCounter extends Model
{
    use HasFactory;

    public $timestamps = false;

    protected $fillable = [
        'station_id',
        'counter_date',
        'last_queue_number',
        'last_internal_sequence',
    ];

    protected $casts = [
        'counter_date' => 'date',
    ];

    public function station(): BelongsTo
    {
        return $this->belongsTo(Station::class);
    }

    /**
     * Atomically increment the queue number and return the new value.
     * Uses row-level locking for concurrency safety.
     */
    public function incrementQueueNumberAndGet(): int
    {
        // Lock the row, increment, and return the new value in one atomic operation
        return DB::transaction(function (): int {
            /** @var static $counter */
            $counter = static::lockForUpdate()
                ->firstOrCreate([
                    'station_id' => $this->station_id,
                    'counter_date' => $this->counter_date,
                ], [
                    'last_queue_number' => 0,
                    'last_internal_sequence' => 0,
                ]);

            $counter->last_queue_number += 1;
            $counter->save();

            return $counter->last_queue_number;
        });
    }

    /**
     * Atomically increment the internal sequence and return the new value.
     * Uses row-level locking for concurrency safety.
     */
    public function incrementInternalSequenceAndGet(): int
    {
        // Lock the row, increment, and return the new value in one atomic operation
        return DB::transaction(function (): int {
            /** @var static $counter */
            $counter = static::lockForUpdate()
                ->firstOrCreate([
                    'station_id' => $this->station_id,
                    'counter_date' => $this->counter_date,
                ], [
                    'last_queue_number' => 0,
                    'last_internal_sequence' => 0,
                ]);

            $counter->last_internal_sequence += 1;
            $counter->save();

            return $counter->last_internal_sequence;
        });
    }
}