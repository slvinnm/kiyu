<?php

namespace Database\Factories;

use App\Enums\Priority;
use App\Enums\QueueTicketStatus;
use App\Models\QueueTicket;
use App\Models\Visit;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<QueueTicket>
 */
class QueueTicketFactory extends Factory
{
    protected $model = QueueTicket::class;

    public function definition(): array
    {
        return [
            'visit_id' => Visit::factory(),
            'workflow_step_id' => 1,
            'station_id' => 1,
            'queue_number' => 'A' . str_pad((string) fake()->numberBetween(1, 999), 3, '0', STR_PAD_LEFT),
            'priority' => Priority::Normal,
            'internal_sequence' => fake()->numberBetween(1, 999),
            'status' => QueueTicketStatus::Created,
            'called_at' => null,
            'started_at' => null,
            'completed_at' => null,
        ];
    }

    public function called(): static
    {
        return $this->state(fn(array $attributes) => [
            'status' => QueueTicketStatus::Called,
            'called_at' => now(),
        ]);
    }

    public function inProgress(): static
    {
        return $this->state(fn(array $attributes) => [
            'status' => QueueTicketStatus::InProgress,
            'called_at' => now()->subMinutes(10),
            'started_at' => now()->subMinutes(5),
        ]);
    }

    public function completed(): static
    {
        return $this->state(fn(array $attributes) => [
            'status' => QueueTicketStatus::Completed,
            'called_at' => now()->subMinutes(30),
            'started_at' => now()->subMinutes(20),
            'completed_at' => now(),
        ]);
    }
}
