<?php

namespace App\Policies;

use App\Enums\StationType;
use App\Enums\UserRole;
use App\Models\Station;
use App\Models\User;

class StationPolicy
{
    public function view(User $user, Station $station): bool
    {
        return $this->canOperate($user, $station);
    }

    public function callNext(User $user, Station $station): bool
    {
        return $this->canOperate($user, $station);
    }

    public function canOperate(User $user, Station $station): bool
    {
        if ($user->role === UserRole::ADMIN) {
            return true;
        }

        if ($user->station_id !== $station->id) {
            return false;
        }

        if ($station->getRawOriginal('type') === null) {
            return in_array($user->role, [
                UserRole::RECEPTIONIST,
                UserRole::NURSE,
                UserRole::DOCTOR,
                UserRole::PHARMACY,
                UserRole::LAB,
                UserRole::STAFF,
            ], true);
        }

        return match ($user->role) {
            UserRole::RECEPTIONIST => $station->type === StationType::REGISTRATION,
            UserRole::NURSE => $station->type === StationType::NURSE,
            UserRole::DOCTOR => $station->type === StationType::DOCTOR,
            UserRole::LAB => $station->type === StationType::LABORATORY,
            UserRole::PHARMACY => $station->type === StationType::PHARMACY,
            UserRole::STAFF => true,
            default => false,
        };
    }
}
