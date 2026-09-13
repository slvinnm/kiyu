<?php

namespace Database\Factories;

use App\Enums\IntakeChannel;
use App\Enums\Priority;
use App\Enums\VisitStatus;
use App\Models\Patient;
use App\Models\Visit;
use Illuminate\Database\Eloquent\Factories\Factory;

class VisitFactory extends Factory
{
    protected $model = Visit::class;

    public function definition(): array
    {
        return [
            'patient_id' => Patient::factory(),
            'department_id' => 1,
            'workflow_version_id' => 1,
            'visit_number' => 'V-'.now()->format('Ymd').'-'.str_pad($this->faker->unique()->numberBetween(1, 9999), 4, '0', STR_PAD_LEFT),
            'priority' => Priority::NORMAL,
            'intake_channel' => IntakeChannel::WALK_IN,
            'status' => VisitStatus::WAITING,
            'online_active_key' => null,
            'registered_by' => null,
            'checked_in_at' => null,
            'completed_at' => null,
        ];
    }
}
