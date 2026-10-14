<?php

namespace App\Exception;

use App\Entity\QueueEntry;

final class DuplicateTokenException extends QueueException
{
    public function __construct(private readonly QueueEntry $existingEntry)
    {
        parent::__construct('This phone number already has an active token for this doctor today.');
    }

    public function getExistingEntry(): QueueEntry
    {
        return $this->existingEntry;
    }
}
