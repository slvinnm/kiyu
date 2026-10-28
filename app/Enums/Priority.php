<?php

namespace App\Enums;

enum Priority: int
{
    case Normal = 1;
    case Priority = 2;
    case Emergency = 3;

    /**
     * Get label for priority level.
     */
    public function label(): string
    {
        return match ($this) {
            self::Normal => 'NORMAL',
            self::Priority => 'PRIORITY',
            self::Emergency => 'EMERGENCY',
        };
    }
}
