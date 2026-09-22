<?php

namespace App\Policies;

use App\Enums\QueueAcquisitionStatus;
use App\Enums\StationType;
use App\Enums\UserRole;
use App\Models\QueueTicket;
use App\Models\Station;
use App\Models\User;

class QueueTicketPolicy
{
    public function view(User $user, QueueTicket $ticket): bool
    {
        $station = $ticket->relationLoaded('station')
            ? $ticket->station
            : Station::query()->find($ticket->station_id);

        if (! $station instanceof Station) {
            return $user->role !== UserRole::PATIENT
                && $user->station_id === $ticket->station_id;
        }

        if ($user->role === UserRole::RECEPTIONIST) {
            $ticket->loadMissing('visit.queueAcquisition');

            return $station->type === StationType::REGISTRATION
                && $ticket->visit?->queueAcquisition?->status === QueueAcquisitionStatus::ACQUIRED;
        }

        return app(StationPolicy::class)->canOperate($user, $station);
    }

    public function manage(User $user, QueueTicket $ticket): bool
    {
        return $this->view($user, $ticket);
    }
}
