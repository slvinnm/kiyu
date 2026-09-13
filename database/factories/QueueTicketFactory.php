<?php

namespace Database\Factories;

use App\Enums\Priority;
use App\Enums\QueueStatus;
use App\Models\QueueTicket;
use App\Models\Visit;
use Illuminate\Database\Eloquent\Factories\Factory;

class QueueTicketFactory extends Factory
{
    protected $model = QueueTicket::class;

    public function definition(): array
    {
        return [
            'visit_id' => Visit::factory(),
            'visit_workflow_step_id' => 1,
            'station_id' => 1,
            'queue_number' => 'A-'.str_pad($this->faker->unique()->numberBetween(1, 999), 3, '0', STR_PAD_LEFT),
            'priority' => Priority::NORMAL,
            'internal_sequence' => 1,
            'status' => QueueStatus::CREATED,
            'called_at' => null,
            'started_at' => null,
            'completed_at' => null,
            'assigned_to' => null,
            'transferred_from_ticket_id' => null,
            'transferred_to_station_id' => null,
            'notes' => null,
        ];
    }
}
