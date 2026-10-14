<?php

namespace App\Service\Queue;

use Psr\Clock\ClockInterface;

final class QueueDateProvider
{
    public function __construct(
        private readonly ClockInterface $clock,
        private readonly string $timezone,
    ) {
    }

    public function today(): \DateTimeImmutable
    {
        $local = $this->clock->now()->setTimezone(new \DateTimeZone($this->timezone));

        return $local->setTime(0, 0);
    }
}
