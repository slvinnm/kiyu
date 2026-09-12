<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\DB;

class VisitCounter extends Model
{
    use HasFactory;

    public $timestamps = false;

    protected $fillable = [
        'counter_date',
    ];

    public function scopeTodayCounter($query)
    {
        $date = now()->format('Y-m-d');

        return $query->where('counter_date', $date);
    }

    /**
     * Atomically increment and return the new visit number.
     */
    public function incrementAndGet(): int
    {
        return DB::transaction(function (): int {
            $counterDate = now()->format('Y-m-d');

            static::query()->insertOrIgnore([
                'counter_date' => $counterDate,
                'last_number' => 0,
            ]);

            $counter = static::where('counter_date', $counterDate)
                ->lockForUpdate()
                ->firstOrFail();

            $counter->last_number += 1;
            $counter->save();

            return $counter->last_number;
        });
    }
}
