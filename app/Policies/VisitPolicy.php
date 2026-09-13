<?php

namespace App\Policies;

use App\Enums\UserRole;
use App\Models\User;
use App\Models\Visit;

class VisitPolicy
{
    public function view(User $user, Visit $visit): bool
    {
        if ($user->role !== UserRole::PATIENT) {
            return false;
        }

        return $user->patient?->id === $visit->patient_id;
    }

    public function checkIn(User $user, Visit $visit): bool
    {
        return $this->view($user, $visit);
    }

    public function setPriority(User $user, Visit $visit): bool
    {
        if ($user->role === UserRole::ADMIN) {
            return true;
        }

        return in_array($user->role, [
            UserRole::DOCTOR,
            UserRole::NURSE,
        ], true) && $user->department_id === $visit->department_id;
    }
}
