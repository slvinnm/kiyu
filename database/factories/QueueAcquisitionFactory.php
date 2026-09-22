<?php

namespace Database\Factories;

use App\Models\QueueAcquisition;
use App\Models\Visit;
use Illuminate\Database\Eloquent\Factories\Factory;

class QueueAcquisitionFactory extends Factory
{
    protected $model = QueueAcquisition::class;

    public function definition(): array
    {
        return [
            'department_id' => 1,
            'visit_id' => Visit::factory(),
            'channel' => 'KIOSK',
            'idempotency_key' => null,
            'status' => 'ACQUIRED',
            'registered_by' => null,
            'acquired_at' => now(),
            'registered_at' => null,
        ];
    }
}
