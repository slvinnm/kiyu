<?php

namespace App\Policies;

use App\Enums\UserRole;
use App\Models\Referral;
use App\Models\User;

class ReferralPolicy
{
    public function create(User $user, Referral $referral): bool
    {
        if (! in_array($user->role, [
            UserRole::ADMIN,
            UserRole::DOCTOR,
            UserRole::NURSE,
        ], true)) {
            return false;
        }

        return $user->role === UserRole::ADMIN
            || $user->department_id === $referral->sourceVisit?->department_id;
    }

    public function view(User $user, Referral $referral): bool
    {
        if ($user->role === UserRole::ADMIN) {
            return true;
        }

        if (! in_array($user->role, [
            UserRole::RECEPTIONIST,
            UserRole::NURSE,
            UserRole::DOCTOR,
            UserRole::PHARMACY,
            UserRole::LAB,
            UserRole::STAFF,
        ], true)) {
            return false;
        }

        return $user->department_id === $referral->sourceVisit?->department_id
            || $user->department_id === $referral->target_department_id;
    }
}
