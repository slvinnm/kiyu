<?php

namespace App\Services;

use App\Enums\QueueEventType;
use App\Enums\QueueStatus;
use App\Models\QueueEvent;
use App\Models\QueueTicket;

class QueueStateMachine
{
    protected array $validTransitions = [
        QueueStatus::CREATED->value => [
            QueueStatus::CALLED->value,
            QueueStatus::SKIPPED->value,
            QueueStatus::CANCELLED->value,
            QueueStatus::NO_SHOW->value,
            QueueStatus::TRANSFERRED->value,
        ],
        QueueStatus::CALLED->value => [
            QueueStatus::IN_PROGRESS->value,
            QueueStatus::ON_HOLD->value,
            QueueStatus::SKIPPED->value,
            QueueStatus::CANCELLED->value,
            QueueStatus::TRANSFERRED->value,
        ],
        QueueStatus::IN_PROGRESS->value => [
            QueueStatus::COMPLETED->value,
            QueueStatus::ON_HOLD->value,
            QueueStatus::SKIPPED->value,
            QueueStatus::CANCELLED->value,
            QueueStatus::TRANSFERRED->value,
        ],
        QueueStatus::ON_HOLD->value => [
            QueueStatus::CALLED->value,
            QueueStatus::CANCELLED->value,
        ],
        QueueStatus::COMPLETED->value => [],
        QueueStatus::SKIPPED->value => [
            QueueStatus::CANCELLED->value,
        ],
        QueueStatus::CANCELLED->value => [],
        QueueStatus::NO_SHOW->value => [],
        QueueStatus::TRANSFERRED->value => [],
    ];

    public function canTransition(QueueTicket $ticket, QueueStatus $to): bool
    {
        $from = $ticket->status;

        if ($from->value === $to->value) {
            return false;
        }

        $allowed = $this->validTransitions[$from->value] ?? [];

        return in_array($to->value, $allowed, true);
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

        QueueEvent::create([
            'queue_ticket_id' => $ticket->id,
            'event_type' => match ($to) {
                QueueStatus::CALLED => $fromValue === QueueStatus::ON_HOLD->value
                    ? QueueEventType::RESUMED
                    : QueueEventType::CALLED,
                QueueStatus::IN_PROGRESS => QueueEventType::STARTED,
                QueueStatus::COMPLETED => QueueEventType::COMPLETED,
                QueueStatus::ON_HOLD => QueueEventType::HELD,
                QueueStatus::SKIPPED => QueueEventType::SKIPPED,
                QueueStatus::CANCELLED => QueueEventType::CANCELLED,
                QueueStatus::TRANSFERRED => QueueEventType::TRANSFERRED,
                QueueStatus::NO_SHOW => QueueEventType::NO_SHOW,
                default => throw new \LogicException("No queue event mapping exists for {$toValue}."),
            },
            'from_status' => $fromValue,
            'to_status' => $toValue,
            'user_id' => $byUserId,
        ]);

        return true;
    }

    public function isEligibleForCall(QueueTicket $ticket): bool
    {
        return $ticket->status === QueueStatus::CREATED;
    }

    public function isEligibleForNextSelection(QueueTicket $ticket): bool
    {
        return $ticket->status === QueueStatus::CREATED;
    }

    public function isEligibleForResume(QueueTicket $ticket): bool
    {
        return $ticket->status === QueueStatus::ON_HOLD;
    }

    public function isEligibleToStart(QueueTicket $ticket): bool
    {
        return $ticket->status === QueueStatus::CALLED;
    }

    public function isEligibleForCompletion(QueueTicket $ticket): bool
    {
        return $ticket->status === QueueStatus::IN_PROGRESS;
    }

    public function isEligibleForSkip(QueueTicket $ticket): bool
    {
        return in_array($ticket->status, [
            QueueStatus::CREATED,
            QueueStatus::CALLED,
            QueueStatus::IN_PROGRESS,
        ], true);
    }
}
