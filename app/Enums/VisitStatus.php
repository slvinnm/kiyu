<?php

namespace App\Enums;

enum VisitStatus: string
{
    case AwaitingCheckin = 'AWAITING_CHECKIN';
    case Waiting = 'WAITING';
    case InProgress = 'IN_PROGRESS';
    case Completed = 'COMPLETED';
    case Cancelled = 'CANCELLED';
}
