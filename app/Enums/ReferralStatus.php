<?php

namespace App\Enums;

enum ReferralStatus: string
{
    case Pending = 'PENDING';
    case Accepted = 'ACCEPTED';
    case Completed = 'COMPLETED';
    case Cancelled = 'CANCELLED';
}
