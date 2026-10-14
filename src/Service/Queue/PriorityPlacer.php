<?php

namespace App\Service\Queue;

use App\Entity\QueueEntry;
use App\Enum\PriorityRule;
use App\Enum\QueueStatus;

final class PriorityPlacer
{
    /**
     * Index in the ordered queue (WAITING and CALLED entries) where a priority patient is inserted.
     *
     * @param list<QueueEntry> $queued
     */
    public function priorityIndex(array $queued, PriorityRule $rule, int $afterN): int
    {
        $calledCount = $this->leadingCalledCount($queued);
        $waiting = \array_slice($queued, $calledCount);
        $flags = array_map(static fn (QueueEntry $entry): bool => $entry->isPriority(), $waiting);

        return $calledCount + match ($rule) {
            PriorityRule::NEXT_IN_LINE => $this->leadingTrueCount($flags),
            PriorityRule::AFTER_N => min(max(0, $afterN), \count($flags)),
        };
    }

    /**
     * Index where a recalled (previously skipped) patient is re-inserted.
     *
     * @param list<QueueEntry> $queued
     */
    public function recallIndex(array $queued, bool $afterCurrent): int
    {
        return $afterCurrent ? $this->leadingCalledCount($queued) : 0;
    }

    /** @param list<QueueEntry> $queued */
    private function leadingCalledCount(array $queued): int
    {
        $count = 0;
        foreach ($queued as $entry) {
            if (QueueStatus::CALLED !== $entry->getStatus()) {
                break;
            }
            ++$count;
        }

        return $count;
    }

    /** @param list<bool> $flags */
    private function leadingTrueCount(array $flags): int
    {
        $count = 0;
        foreach ($flags as $flag) {
            if (!$flag) {
                break;
            }
            ++$count;
        }

        return $count;
    }
}
