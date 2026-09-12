<?php

namespace App\Policies;

use App\Enums\UserRole;
use App\Models\QueueTicket;
use App\Models\User;

class QueueTicketPolicy
{
    public function view(User $user, QueueTicket $ticket): bool
    {
        return $user->role === UserRole::ADMIN
            || $user->station_id === $ticket->station_id;
    }

    public function manage(User $user, QueueTicket $ticket): bool
    {
        return $this->view($user, $ticket);
    }
}
