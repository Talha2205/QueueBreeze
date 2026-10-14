<?php

namespace App\Enum;

enum QueueAction: string
{
    case JOINED = 'JOINED';
    case WALK_IN_ADDED = 'WALK_IN_ADDED';
    case PRIORITY_ADDED = 'PRIORITY_ADDED';
    case PRIORITY_MARKED = 'PRIORITY_MARKED';
    case CALLED = 'CALLED';
    case STARTED = 'STARTED';
    case COMPLETED = 'COMPLETED';
    case SKIPPED = 'SKIPPED';
    case RECALLED = 'RECALLED';
    case CANCELLED = 'CANCELLED';
    case CHECKED_IN = 'CHECKED_IN';
}
