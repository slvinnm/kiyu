<?php

namespace Database\Factories;

use App\Models\QueueCounter;
use App\Models\Station;
use Illuminate\Database\Eloquent\Factories\Factory;

class QueueCounterFactory extends Factory
{
    protected $model = QueueCounter::class;

    public function definition(): array
    {
        return [
            'station_id' => Station::factory(),
            'counter_date' => now()->format('Y-m-d'),
            'last_queue_number' => 0,
            'last_internal_sequence' => 0,
        ];
    }
}
