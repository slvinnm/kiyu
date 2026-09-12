<?php

namespace App\Enums;

enum VisitStatus: string
{
    case AWAITING_CHECKIN = 'AWAITING_CHECKIN';
    case CHECKED_IN = 'CHECKED_IN';
    case IN_PROGRESS = 'IN_PROGRESS';
    case COMPLETED = 'COMPLETED';
    case CANCELLED = 'CANCELLED';

    public function label(): string
    {
        return match ($this) {
            self::AWAITING_CHECKIN => 'Awaiting Check-in',
            self::CHECKED_IN => 'Checked In',
            self::IN_PROGRESS => 'In Progress',
            self::COMPLETED => 'Completed',
            self::CANCELLED => 'Cancelled',
        };
    }
}
