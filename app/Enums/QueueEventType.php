<?php

namespace App\Enums;

enum QueueEventType: string
{
    case Created = 'CREATED';
    case Called = 'CALLED';
    case Recalled = 'RECALLED';
    case Started = 'STARTED';
    case Completed = 'COMPLETED';
    case Held = 'HELD';
    case Resumed = 'RESUMED';
    case Skipped = 'SKIPPED';
    case Cancelled = 'CANCELLED';
    case NoShow = 'NO_SHOW';
    case Transferred = 'TRANSFERRED';
}
