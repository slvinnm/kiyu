<?php

namespace App\Enums;

enum QueueTicketStatus: string
{
    case Created = 'CREATED';
    case Called = 'CALLED';
    case InProgress = 'IN_PROGRESS';
    case OnHold = 'ON_HOLD';
    case Completed = 'COMPLETED';
    case Skipped = 'SKIPPED';
    case Cancelled = 'CANCELLED';
    case NoShow = 'NO_SHOW';
    case Transferred = 'TRANSFERRED';

    /**
     * Determine if ticket is eligible for call-next.
     */
    public function isCallable(): bool
    {
        return $this === self::Created;
    }

    /**
     * Determine if ticket is an active / pending ticket.
     */
    public function isActive(): bool
    {
        return in_array($this, [self::Created, self::Called, self::InProgress, self::OnHold], true);
    }

    /**
     * Determine if ticket is historical (completed, skipped, cancelled, no-show, transferred).
     */
    public function isHistorical(): bool
    {
        return ! $this->isActive();
    }
}
