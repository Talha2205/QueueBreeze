<?php

namespace App\Enum;

enum QueueStatus: string
{
    case WAITING = 'WAITING';
    case CALLED = 'CALLED';
    case IN_PROGRESS = 'IN_PROGRESS';
    case COMPLETED = 'COMPLETED';
    case SKIPPED = 'SKIPPED';
    case CANCELLED = 'CANCELLED';

    public function isQueued(): bool
    {
        return self::WAITING === $this || self::CALLED === $this;
    }

    public function isActive(): bool
    {
        return $this->isQueued() || self::IN_PROGRESS === $this;
    }

    public function canTransitionTo(self $target): bool
    {
        return \in_array($target, match ($this) {
            self::WAITING => [self::CALLED, self::SKIPPED, self::CANCELLED],
            self::CALLED => [self::IN_PROGRESS, self::SKIPPED, self::CANCELLED],
            self::IN_PROGRESS => [self::COMPLETED, self::CANCELLED],
            self::SKIPPED => [self::WAITING],
            self::COMPLETED, self::CANCELLED => [],
        }, true);
    }

    public function label(): string
    {
        return match ($this) {
            self::WAITING => 'Waiting',
            self::CALLED => 'Called',
            self::IN_PROGRESS => 'In Progress',
            self::COMPLETED => 'Completed',
            self::SKIPPED => 'Skipped',
            self::CANCELLED => 'Cancelled',
        };
    }
}
