<?php

namespace App\Policies;

use App\Enums\QueueStatus;
use App\Enums\UserRole;
use App\Models\QueueTicket;
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

    public function refer(User $user, Visit $visit): bool
    {
        if ($user->role === UserRole::ADMIN) {
            return true;
        }

        if (! in_array($user->role, [UserRole::DOCTOR, UserRole::NURSE], true)) {
            return false;
        }

        return $user->department_id === $visit->department_id
            && $user->station_id !== null
            && QueueTicket::query()
            ->whereBelongsTo($visit)
            ->where('station_id', $user->station_id)
            ->whereIn('status', [
                QueueStatus::CREATED->value,
                QueueStatus::CALLED->value,
                QueueStatus::IN_PROGRESS->value,
                QueueStatus::ON_HOLD->value,
            ])
            ->exists();
    }
}
