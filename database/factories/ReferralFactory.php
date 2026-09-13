<?php

namespace Database\Factories;

use App\Enums\Priority;
use App\Enums\ReferralStatus;
use App\Models\Department;
use App\Models\Referral;
use App\Models\User;
use App\Models\Visit;
use Illuminate\Database\Eloquent\Factories\Factory;

class ReferralFactory extends Factory
{
    protected $model = Referral::class;

    public function definition(): array
    {
        return [
            'source_visit_id' => Visit::factory(),
            'target_department_id' => Department::factory(),
            'target_visit_id' => null,
            'referred_by_user_id' => User::factory(),
            'status' => ReferralStatus::PENDING,
            'reason' => fake()->sentence(),
            'priority' => Priority::NORMAL,
        ];
    }
}
