<?php

namespace App\Policies;

use App\Enums\UserRole;
use App\Models\QueueTicket;
use App\Models\User;

class QueueTicketPolicy
{
    public function view(User $user, QueueTicket $ticket): bool
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

        return $user->station_id === $ticket->station_id;
    }

    public function manage(User $user, QueueTicket $ticket): bool
    {
        return $this->view($user, $ticket);
    }
}
