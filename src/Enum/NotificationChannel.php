<?php

namespace App\Enum;

enum NotificationChannel: string
{
    case SMS = 'SMS';
    case WHATSAPP = 'WHATSAPP';
}
