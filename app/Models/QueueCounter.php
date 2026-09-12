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
        'last_number',
    ];

    protected $casts = [
        'counter_date' => 'date',
    ];

    public function station(): BelongsTo
    {
        return $this->belongsTo(Station::class);
    }

    /**
     * Atomically increment and return the new counter value.
     * Uses row-level locking for concurrency safety.
     */
    public function incrementAndGet(): int
    {
        // Lock the row, increment, and return the new value in one atomic operation
        return DB::transaction(function (): int {
            /** @var static $counter */
            $counter = static::lockForUpdate()
                ->firstOrCreate([
                    'station_id' => $this->station_id,
                    'counter_date' => $this->counter_date,
                ], [
                    'last_number' => 0,
                ]);

            $counter->last_number += 1;
            $counter->save();

            return $counter->last_number;
        });
    }
}