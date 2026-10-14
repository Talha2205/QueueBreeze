<?php

namespace App\Enum;

enum PriorityRule: string
{
    case NEXT_IN_LINE = 'NEXT_IN_LINE';
    case AFTER_N = 'AFTER_N';

    public function label(): string
    {
        return match ($this) {
            self::NEXT_IN_LINE => 'Next in line (after current patient)',
            self::AFTER_N => 'After N waiting patients',
        };
    }
}
