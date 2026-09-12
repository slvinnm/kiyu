<?php

namespace App\Enums;

enum QueueAcquisitionStatus: string
{
    case ACQUIRED = 'ACQUIRED';
    case REGISTERED = 'REGISTERED';
    case CANCELLED = 'CANCELLED';
}
