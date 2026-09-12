<?php

namespace App\Policies;

use App\Enums\UserRole;
use App\Models\Station;
use App\Models\User;

class StationPolicy
{
    public function view(User $user, Station $station): bool
    {
        return $user->role === UserRole::ADMIN
            || $user->station_id === $station->id;
    }

    public function callNext(User $user, Station $station): bool
    {
        return $this->view($user, $station);
    }
}
