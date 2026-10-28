<?php

namespace App\Enums;

enum IntakeChannel: string
{
    case Online = 'ONLINE';
    case Kiosk = 'KIOSK';
    case Manual = 'MANUAL';
}
