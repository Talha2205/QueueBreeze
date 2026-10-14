<?php

namespace App\Message;

use App\Enum\NotificationType;

final readonly class SendQueueNotification
{
    public function __construct(
        public int $queueEntryId,
        public NotificationType $type,
    ) {
    }
}
