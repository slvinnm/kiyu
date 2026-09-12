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
        QueueEvent::create([
            'queue_ticket_id' => $ticket->id,
            'event_type' => match ($to) {
                QueueStatus::CALLED => QueueEventType::CALLED,
                QueueStatus::IN_PROGRESS => QueueEventType::STARTED,
                QueueStatus::COMPLETED => QueueEventType::COMPLETED,
                QueueStatus::ON_HOLD => QueueEventType::HELD,
                QueueStatus::SKIPPED => QueueEventType::SKIPPED,
                QueueStatus::CANCELLED => QueueEventType::CANCELLED,
                QueueStatus::TRANSFERRED => QueueEventType::TRANSFERRED,
                default => QueueEventType::CREATED,
            },
            'from_status' => $fromValue,
            'to_status' => $toValue,
            'user_id' => $byUserId,
        ]);

        return true;
    }

    /**
     * Eligibility for callNext():
     * Only CREATED tickets are eligible.
     * ON_HOLD must never be selected by callNext().
     * CALLED tickets must not be re-selected.
     */
    public function isEligibleForCall(QueueTicket $ticket): bool
    {
        return $ticket->status === QueueStatus::CREATED;
    }

    /**
     * Eligibility for callNext selection:
     * Only CREATED status is eligible.
     * This is the invariant for callNext().
     */
    public function isEligibleForNextSelection(QueueTicket $ticket): bool
    {
        return $ticket->status === QueueStatus::CREATED;
    }

    /**
     * Eligibility for resume:
     * Only ON_HOLD → CALLED is valid.
     */
    public function isEligibleForResume(QueueTicket $ticket): bool
    {
        return $ticket->status === QueueStatus::ON_HOLD;
    }

    /**
     * Eligibility for start (CALLED → IN_PROGRESS).
     */
    public function isEligibleToStart(QueueTicket $ticket): bool
    {
        return $ticket->status === QueueStatus::CALLED;
    }

    /**
     * Eligibility for completion (IN_PROGRESS → COMPLETED).
     */
    public function isEligibleForCompletion(QueueTicket $ticket): bool
    {
        return $ticket->status === QueueStatus::IN_PROGRESS;
    }
}
