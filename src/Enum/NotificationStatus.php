<?php

namespace App\Enum;

enum NotificationStatus: string
{
    case SENT = 'SENT';
    case FAILED = 'FAILED';
}
