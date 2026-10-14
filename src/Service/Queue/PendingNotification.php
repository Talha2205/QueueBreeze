<?php

namespace App\Service\Queue;

use App\Entity\QueueEntry;
use App\Enum\NotificationType;

final readonly class PendingNotification
{
    public function __construct(
        public QueueEntry $entry,
        public NotificationType $type,
    ) {
    }
}
