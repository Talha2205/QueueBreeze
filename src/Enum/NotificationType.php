<?php

namespace App\Enum;

enum NotificationType: string
{
    case JOINED = 'JOINED';
    case APPROACHING = 'APPROACHING';
    case CALLED = 'CALLED';
}
