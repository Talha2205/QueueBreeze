<?php

namespace App\Enum;

enum NotificationProvider: string
{
    case LOG = 'log';
    case TWILIO = 'twilio';

    public function label(): string
    {
        return match ($this) {
            self::LOG => 'Log only (development)',
            self::TWILIO => 'Twilio (SMS and WhatsApp)',
        };
    }
}
