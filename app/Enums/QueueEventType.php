<?php

namespace App\Enums;

enum QueueEventType: string
{
    case CREATED = 'CREATED';
    case CALLED = 'CALLED';
    case STARTED = 'STARTED';
    case COMPLETED = 'COMPLETED';
    case HELD = 'HELD';
    case RESUMED = 'RESUMED';
    case SKIPPED = 'SKIPPED';
    case CANCELLED = 'CANCELLED';
    case NO_SHOW = 'NO_SHOW';
    case TRANSFERRED = 'TRANSFERRED';

    public function label(): string
    {
        return match ($this) {
            self::CREATED => 'Ticket Created',
            self::CALLED => 'Patient Called',
            self::STARTED => 'Service Started',
            self::COMPLETED => 'Service Completed',
            self::HELD => 'Put on Hold',
            self::RESUMED => 'Resumed',
            self::SKIPPED => 'Skipped',
            self::CANCELLED => 'Cancelled',
            self::NO_SHOW => 'Marked as No Show',
            self::TRANSFERRED => 'Transferred',
        };
    }
}
