<?php

namespace App\Services;

use App\Enums\QueueStatus;
use App\Models\QueueTicket;
use Illuminate\Support\Facades\DB;

class QueueStateMachine
{
    protected array $validTransitions = [
        QueueStatus::CREATED->value => [
            QueueStatus::CALLED->value,
            QueueStatus::SKIPPED->value,
            QueueStatus::CANCELLED->value,
            QueueStatus::NO_SHOW->value,
        ],
        QueueStatus::CALLED->value => [
            QueueStatus::IN_PROGRESS->value,
            QueueStatus::ON_HOLD->value,
            QueueStatus::SKIPPED->value,
            QueueStatus::CANCELLED->value,
            QueueStatus::NO_SHOW->value,
        ],
        QueueStatus::IN_PROGRESS->value => [
            QueueStatus::COMPLETED->value,
            QueueStatus::ON_HOLD->value,
            QueueStatus::SKIPPED->value,
            QueueStatus::CANCELLED->value,
        ],
        QueueStatus::ON_HOLD->value => [
            QueueStatus::CALLED->value,
            QueueStatus::CANCELLED->value,
        ],
        QueueStatus::COMPLETED->value => [
            QueueStatus::TRANSFERRED->value,
        ],
        QueueStatus::SKIPPED->value => [
            QueueStatus::CANCELLED->value,
        ],
        QueueStatus::CANCELLED->value => [],
        QueueStatus::NO_SHOW->value => [
            QueueStatus::CREATED->value,
        ],
        QueueStatus::TRANSFERRED->value => [],
    ];

    public function canTransition(QueueTicket $ticket, QueueStatus $to): bool
    {
        $from = $ticket->status;
        if ($from === $to) {
            return true;
        }
        $allowed = $this->validTransitions[$from->value] ?? [];
        return in_array($to->value, $allowed);
    }

    public function apply(QueueTicket $ticket, QueueStatus $to, ?int $byUserId = null): bool
    {
        if (! $this->canTransition($ticket, $to)) {
            return false;
        }

        $fromValue = $ticket->status->value;
        $toValue = $to->value;

        $ticket->update([
            'status' => $to,
            'called_at' => $to === QueueStatus::CALLED ? now() : $ticket->called_at,
            'started_at' => $to === QueueStatus::IN_PROGRESS ? now() : $ticket->started_at,
            'completed_at' => $to === QueueStatus::COMPLETED ? now() : $ticket->completed_at,
        ]);

        // Log event
        \App\Models\QueueEvent::create([
            'queue_ticket_id' => $ticket->id,
            'event_type' => match ($to) {
                QueueStatus::CALLED => \App\Enums\QueueEventType::CALLED,
                QueueStatus::IN_PROGRESS => \App\Enums\QueueEventType::STARTED,
                QueueStatus::COMPLETED => \App\Enums\QueueEventType::COMPLETED,
                QueueStatus::ON_HOLD => \App\Enums\QueueEventType::HELD,
                QueueStatus::SKIPPED => \App\Enums\QueueEventType::SKIPPED,
                QueueStatus::CANCELLED => \App\Enums\QueueEventType::CANCELLED,
                QueueStatus::TRANSFERRED => \App\Enums\QueueEventType::TRANSFERRED,
                default => \App\Enums\QueueEventType::CREATED,
            },
            'from_status' => $fromValue,
            'to_status' => $toValue,
            'user_id' => $byUserId,
        ]);

        return true;
    }

    public function isEligibleForCall(QueueTicket $ticket): bool
    {
        return in_array($ticket->status, [
            QueueStatus::CREATED->value,
            QueueStatus::CALLED->value,
            QueueStatus::ON_HOLD->value,
        ]);
    }

    /**
     * Critical invariant: ON_HOLD must NOT be selected by callNext().
     */
    public function isEligibleForNextSelection(QueueTicket $ticket): bool
    {
        return $ticket->status === QueueStatus::CREATED->value || $ticket->status === QueueStatus::CALLED->value;
    }
}
