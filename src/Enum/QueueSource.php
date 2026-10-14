<?php

namespace App\Enum;

enum QueueSource: string
{
    case ONLINE = 'ONLINE';
    case WALK_IN = 'WALK_IN';
}
