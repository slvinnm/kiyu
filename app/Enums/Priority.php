<?php

namespace App\Enums;

enum Priority: int
{
    case NORMAL = 1;
    case PRIORITY = 2;
    case EMERGENCY = 3;

    public function label(): string
    {
        return match ($this) {
            self::NORMAL => 'Normal',
            self::PRIORITY => 'Priority (Elderly/Pregnant/Disabled)',
            self::EMERGENCY => 'Emergency',
        };
    }
}
