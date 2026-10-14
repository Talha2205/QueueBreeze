<?php

namespace App\Service\Queue;

use App\Entity\QueueEntry;
use App\Enum\QueueSource;
use App\Enum\QueueStatus;

final class DailyReportBuilder
{
    /**
     * @param list<QueueEntry> $entries
     *
     * @return array{total: int, served: int, walkIns: int, online: int, priority: int, skipped: int, cancelled: int, avgWaitMinutes: ?float, avgConsultationMinutes: ?float}
     */
    public function summarize(array $entries): array
    {
        $summary = ['total' => \count($entries), 'served' => 0, 'walkIns' => 0, 'online' => 0, 'priority' => 0, 'skipped' => 0, 'cancelled' => 0];
        $waits = [];
        $consultations = [];

        foreach ($entries as $entry) {
            if (QueueStatus::COMPLETED === $entry->getStatus()) {
                ++$summary['served'];
            }
            if (QueueStatus::CANCELLED === $entry->getStatus()) {
                ++$summary['cancelled'];
            }
            if (QueueSource::WALK_IN === $entry->getSource()) {
                ++$summary['walkIns'];
            } else {
                ++$summary['online'];
            }
            if ($entry->isPriority()) {
                ++$summary['priority'];
            }
            if (null !== $entry->getSkippedAt()) {
                ++$summary['skipped'];
            }
            if (null !== $entry->getStartedAt()) {
                $waits[] = $this->minutesBetween($entry->getJoinedAt(), $entry->getStartedAt());
            }
            if (null !== $entry->getStartedAt() && null !== $entry->getCompletedAt()) {
                $consultations[] = $this->minutesBetween($entry->getStartedAt(), $entry->getCompletedAt());
            }
        }

        return $summary + [
            'avgWaitMinutes' => $this->average($waits),
            'avgConsultationMinutes' => $this->average($consultations),
        ];
    }

    /**
     * @param list<QueueEntry> $entries
     *
     * @return array<int, list<QueueEntry>> entries grouped by doctor id
     */
    public function groupByDoctor(array $entries): array
    {
        $groups = [];
        foreach ($entries as $entry) {
            $groups[$entry->getDoctor()->getId()][] = $entry;
        }

        return $groups;
    }

    private function minutesBetween(\DateTimeImmutable $from, \DateTimeImmutable $to): float
    {
        return ($to->getTimestamp() - $from->getTimestamp()) / 60;
    }

    /** @param list<float> $values */
    private function average(array $values): ?float
    {
        if ([] === $values) {
            return null;
        }

        return round(array_sum($values) / \count($values), 1);
    }
}
