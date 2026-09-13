<?php

namespace App\Policies;

use App\Enums\UserRole;
use App\Models\Station;
use App\Models\User;

class StationPolicy
{
    public function view(User $user, Station $station): bool
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

        return $user->station_id === $station->id;
    }

    public function callNext(User $user, Station $station): bool
    {
        return $this->view($user, $station);
    }
}
