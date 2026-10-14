<?php

namespace App\Enum;

enum RecallPlacement: string
{
    case FRONT = 'FRONT';
    case AFTER_CURRENT = 'AFTER_CURRENT';

    public function label(): string
    {
        return match ($this) {
            self::FRONT => 'Front of the queue',
            self::AFTER_CURRENT => 'After the current patient',
        };
    }
}
