<?php

namespace App\Enums;

enum QueueStatus: string
{
    case CREATED = 'CREATED';
    case CALLED = 'CALLED';
    case IN_PROGRESS = 'IN_PROGRESS';
    case ON_HOLD = 'ON_HOLD';
    case COMPLETED = 'COMPLETED';
    case SKIPPED = 'SKIPPED';
    case CANCELLED = 'CANCELLED';
    case NO_SHOW = 'NO_SHOW';
    case TRANSFERRED = 'TRANSFERRED';

    public function label(): string
    {
        return match ($this) {
            self::CREATED => 'Created / Waiting',
            self::CALLED => 'Called',
            self::IN_PROGRESS => 'In Progress',
            self::ON_HOLD => 'On Hold',
            self::COMPLETED => 'Completed',
            self::SKIPPED => 'Skipped',
            self::CANCELLED => 'Cancelled',
            self::NO_SHOW => 'No Show',
            self::TRANSFERRED => 'Transferred',
        };
    }
}
