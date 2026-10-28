<?php

namespace Database\Factories;

use App\Enums\IntakeChannel;
use App\Enums\Priority;
use App\Enums\VisitStatus;
use App\Models\Patient;
use App\Models\Visit;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Visit>
 */
class VisitFactory extends Factory
{
    protected $model = Visit::class;

    public function definition(): array
    {
        return [
            'patient_id' => Patient::factory(),
            'department_id' => 1,
            'workflow_id' => 1,
            'visit_number' => 'V-' . now()->format('Ymd') . '-' . str_pad((string) fake()->numberBetween(1, 999), 4, '0', STR_PAD_LEFT),
            'priority' => Priority::Normal,
            'intake_channel' => IntakeChannel::Manual,
            'status' => VisitStatus::Waiting,
            'online_active_key' => null,
            'checked_in_at' => now(),
            'registered_at' => null,
            'completed_at' => null,
        ];
    }

    public function awaitingCheckin(): static
    {
        return $this->state(fn(array $attributes) => [
            'status' => VisitStatus::AwaitingCheckin,
            'checked_in_at' => null,
        ]);
    }

    public function online(): static
    {
        return $this->state(fn(array $attributes) => [
            'intake_channel' => IntakeChannel::Online,
            'status' => VisitStatus::AwaitingCheckin,
        ]);
    }

    public function completed(): static
    {
        return $this->state(fn(array $attributes) => [
            'status' => VisitStatus::Completed,
            'completed_at' => now(),
        ]);
    }
}
