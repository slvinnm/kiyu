<?php

namespace App\Enums;

enum IntakeChannel: string
{
    case ONLINE = 'ONLINE';
    case KIOSK = 'KIOSK';
    case WALK_IN = 'WALK_IN';
    case MANUAL = 'MANUAL';

    public function label(): string
    {
        return match ($this) {
            self::ONLINE => 'Online Registration',
            self::KIOSK => 'Self-Service Kiosk',
            self::WALK_IN => 'Walk-In / Reception',
            self::MANUAL => 'Manual Reception',
        };
    }
}
