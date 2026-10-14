<?php

namespace App\Event;

final readonly class QueueChangedEvent
{
    public function __construct(public int $doctorId)
    {
    }
}
