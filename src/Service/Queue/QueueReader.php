<?php

namespace App\Service\Queue;

use App\Entity\Doctor;
use App\Entity\QueueEntry;
use App\Enum\QueueStatus;
use App\Repository\QueueEntryRepository;

final class QueueReader
{
    private const ROW_ORDER = [
        QueueStatus::IN_PROGRESS->value => 0,
        QueueStatus::WAITING->value => 1,
        QueueStatus::CALLED->value => 1,
        QueueStatus::SKIPPED->value => 2,
        QueueStatus::COMPLETED->value => 3,
        QueueStatus::CANCELLED->value => 4,
    ];

    public function __construct(
        private readonly QueueEntryRepository $entries,
        private readonly WaitTimeCalculator $waitTimes,
    ) {
    }

    /** @return array{position: ?int, ahead: int, waitText: string, nowServing: ?string} */
    public function patientStatus(QueueEntry $entry): array
    {
        $doctor = $entry->getDoctor();
        $queued = $this->entries->findQueued($doctor, $entry->getQueueDate());

        $position = null;
        $ahead = 0;
        foreach ($queued as $index => $candidate) {
            if ($candidate->getId() === $entry->getId()) {
                $position = $index + 1;
                $ahead = $index;
                break;
            }
        }

        [$low, $high] = $this->waitTimes->range(
            $ahead,
            $doctor->getAvgConsultationMinutes(),
            $doctor->getClinic()->getWaitRangeBufferPercent(),
        );

        return [
            'position' => $position,
            'ahead' => $ahead,
            'waitText' => $this->waitTimes->format($low, $high),
            'nowServing' => $this->nowServingToken($doctor, $entry->getQueueDate()),
        ];
    }

    /** @return array{doctor: Doctor, nowServing: ?string, waitingCount: int, waitText: string} */
    public function doctorSummary(Doctor $doctor, \DateTimeImmutable $date): array
    {
        $queued = $this->entries->findQueued($doctor, $date);
        $waitingCount = 0;
        foreach ($queued as $entry) {
            if (QueueStatus::WAITING === $entry->getStatus()) {
                ++$waitingCount;
            }
        }

        [$low, $high] = $this->waitTimes->range(
            \count($queued),
            $doctor->getAvgConsultationMinutes(),
            $doctor->getClinic()->getWaitRangeBufferPercent(),
        );

        return [
            'doctor' => $doctor,
            'nowServing' => $this->nowServingToken($doctor, $date),
            'waitingCount' => $waitingCount,
            'waitText' => $this->waitTimes->format($low, $high),
        ];
    }

    /** @return list<array{entry: QueueEntry, position: ?int}> */
    public function doctorRows(Doctor $doctor, \DateTimeImmutable $date): array
    {
        $all = $this->entries->findForDoctorOnDate($doctor, $date);

        $positions = [];
        $position = 0;
        foreach ($this->entries->findQueued($doctor, $date) as $queued) {
            $positions[$queued->getId()] = ++$position;
        }

        usort($all, static fn (QueueEntry $a, QueueEntry $b): int => [self::ROW_ORDER[$a->getStatus()->value], $a->getSortOrder(), $a->getId()]
            <=> [self::ROW_ORDER[$b->getStatus()->value], $b->getSortOrder(), $b->getId()]);

        return array_map(
            static fn (QueueEntry $entry): array => ['entry' => $entry, 'position' => $positions[$entry->getId()] ?? null],
            $all,
        );
    }

    private function nowServingToken(Doctor $doctor, \DateTimeImmutable $date): ?string
    {
        $inProgress = $this->entries->findInProgress($doctor, $date);
        if (null !== $inProgress) {
            return $inProgress->getTokenNumber();
        }

        foreach ($this->entries->findQueued($doctor, $date) as $queued) {
            if (QueueStatus::CALLED === $queued->getStatus()) {
                return $queued->getTokenNumber();
            }
        }

        return null;
    }
}
