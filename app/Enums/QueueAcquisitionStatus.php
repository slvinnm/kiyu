<?php

namespace App\Enums;

enum QueueAcquisitionStatus: string
{
    case Acquired = 'ACQUIRED';
    case Registered = 'REGISTERED';
    case Cancelled = 'CANCELLED';
}
