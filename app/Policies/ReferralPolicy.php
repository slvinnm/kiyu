<?php

namespace App\Policies;

use App\Enums\UserRole;
use App\Models\Referral;
use App\Models\User;

class ReferralPolicy
{
    public function create(User $user, Referral $referral): bool
    {
        if ($user->role === UserRole::ADMIN) {
            return true;
        }

        return $user->role === UserRole::DOCTOR
            || $user->role === UserRole::NURSE;
    }

    public function view(User $user, Referral $referral): bool
    {
        if ($user->role === UserRole::ADMIN) {
            return true;
        }

        return $user->department_id === $referral->sourceVisit?->department_id
            || $user->department_id === $referral->target_department_id;
    }
}
