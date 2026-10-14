<?php

namespace App\Service\Queue;

use App\Entity\Doctor;
use App\Entity\QueueEntry;
use App\Entity\QueueEvent;
use App\Entity\User;
use App\Enum\NotificationChannel;
use App\Enum\NotificationType;
use App\Enum\QueueAction;
use App\Enum\QueueSource;
use App\Enum\QueueStatus;
use App\Enum\RecallPlacement;
use App\Event\QueueChangedEvent;
use App\Exception\ConsultationInProgressException;
use App\Exception\DoctorUnavailableException;
use App\Exception\DuplicateTokenException;
use App\Exception\InvalidQueueTransitionException;
use App\Message\SendQueueNotification;
use App\Repository\QueueEntryRepository;
use Doctrine\DBAL\LockMode;
use Doctrine\ORM\EntityManagerInterface;
use Psr\Clock\ClockInterface;
use Psr\Log\LoggerInterface;
use Symfony\Component\EventDispatcher\EventDispatcherInterface;
use Symfony\Component\Messenger\MessageBusInterface;

final class QueueService
{
    public function __construct(
        private readonly EntityManagerInterface $em,
        private readonly QueueEntryRepository $entries,
        private readonly QueueDateProvider $dates,
        private readonly PriorityPlacer $placer,
        private readonly MessageBusInterface $bus,
        private readonly EventDispatcherInterface $events,
        private readonly ClockInterface $clock,
        private readonly LoggerInterface $logger,
    ) {
    }

    public function joinOnline(Doctor $doctor, string $name, string $phone, NotificationChannel $channel): QueueEntry
    {
        if (!$doctor->isActive() || !$doctor->isAvailableToday() || !$doctor->getClinic()->isActive()) {
            throw new DoctorUnavailableException('This doctor is not accepting online tokens right now.');
        }

        $date = $this->dates->today();

        return $this->mutate($doctor, $date, function (array &$pending) use ($doctor, $date, $name, $phone, $channel): QueueEntry {
            if (null !== $existing = $this->entries->findActiveForPhone($doctor, $date, $phone)) {
                throw new DuplicateTokenException($existing);
            }

            $entry = $this->createEntry($doctor, $date, $name, QueueSource::ONLINE);
            $entry->setContact($phone, $channel);
            $this->record($entry, QueueAction::JOINED, null);
            $this->insertInLine($entry, $doctor, $date, static fn (array $line): int => \count($line));
            $pending[] = new PendingNotification($entry, NotificationType::JOINED);

            return $entry;
        });
    }

    public function addStaffEntry(
        Doctor $doctor,
        User $actor,
        string $name,
        ?string $phone,
        ?NotificationChannel $channel,
        ?string $priorityReason,
    ): QueueEntry {
        if (!$doctor->isActive()) {
            throw new DoctorUnavailableException('This doctor is inactive.');
        }

        $date = $this->dates->today();

        return $this->mutate($doctor, $date, function (array &$pending) use ($doctor, $date, $actor, $name, $phone, $channel, $priorityReason): QueueEntry {
            $entry = $this->createEntry($doctor, $date, $name, QueueSource::WALK_IN);
            $entry->setContact($phone, $phone ? $channel : null);
            $entry->checkIn($this->clock->now());

            if (null !== $priorityReason) {
                $entry->markPriority($priorityReason, $actor);
                $this->record($entry, QueueAction::PRIORITY_ADDED, $actor);
                $this->insertInLine($entry, $doctor, $date, $this->priorityIndexer($doctor));
            } else {
                $this->record($entry, QueueAction::WALK_IN_ADDED, $actor);
                $this->insertInLine($entry, $doctor, $date, static fn (array $line): int => \count($line));
            }

            if (null !== $entry->getPatientPhone()) {
                $pending[] = new PendingNotification($entry, NotificationType::JOINED);
            }

            return $entry;
        });
    }

    public function markPriority(QueueEntry $entry, string $reason, User $actor): void
    {
        $this->mutate($entry->getDoctor(), $entry->getQueueDate(), function (array &$pending) use ($entry, $reason, $actor): void {
            $this->assertStatus($entry, QueueStatus::WAITING, 'mark priority');
            $entry->markPriority($reason, $actor);
            $this->record($entry, QueueAction::PRIORITY_MARKED, $actor);
            $this->insertInLine($entry, $entry->getDoctor(), $entry->getQueueDate(), $this->priorityIndexer($entry->getDoctor()));
        });
    }

    public function callNext(Doctor $doctor, User $actor): ?QueueEntry
    {
        $date = $this->dates->today();

        return $this->mutate($doctor, $date, function (array &$pending) use ($doctor, $date, $actor): ?QueueEntry {
            $next = $this->nextWaiting($doctor, $date);
            if (null === $next) {
                return null;
            }

            $this->markCalled($next, $actor, $pending);

            return $next;
        });
    }

    public function call(QueueEntry $entry, User $actor): void
    {
        $this->mutate($entry->getDoctor(), $entry->getQueueDate(), function (array &$pending) use ($entry, $actor): void {
            $this->markCalled($entry, $actor, $pending);
        });
    }

    public function start(QueueEntry $entry, User $actor): void
    {
        $doctor = $entry->getDoctor();
        $this->mutate($doctor, $entry->getQueueDate(), function () use ($entry, $actor, $doctor): void {
            if (null !== $this->entries->findInProgress($doctor, $entry->getQueueDate())) {
                throw new ConsultationInProgressException('Another patient is already in consultation with this doctor.');
            }

            $this->transition($entry, QueueStatus::IN_PROGRESS, QueueAction::STARTED, $actor);
            $entry->setStartedAt($this->clock->now());
        });
    }

    public function complete(QueueEntry $entry, User $actor): void
    {
        $this->mutate($entry->getDoctor(), $entry->getQueueDate(), function () use ($entry, $actor): void {
            $this->transition($entry, QueueStatus::COMPLETED, QueueAction::COMPLETED, $actor);
            $entry->setCompletedAt($this->clock->now());
        });
    }

    public function skip(QueueEntry $entry, User $actor): void
    {
        $this->mutate($entry->getDoctor(), $entry->getQueueDate(), function () use ($entry, $actor): void {
            $this->transition($entry, QueueStatus::SKIPPED, QueueAction::SKIPPED, $actor);
            $entry->setSkippedAt($this->clock->now());
        });
    }

    public function recall(QueueEntry $entry, User $actor): void
    {
        $this->mutate($entry->getDoctor(), $entry->getQueueDate(), function () use ($entry, $actor): void {
            $this->transition($entry, QueueStatus::WAITING, QueueAction::RECALLED, $actor);
            $afterCurrent = RecallPlacement::AFTER_CURRENT === $entry->getClinic()->getRecallPlacement();
            $this->insertInLine($entry, $entry->getDoctor(), $entry->getQueueDate(), fn (array $line): int => $this->placer->recallIndex($line, $afterCurrent));
        });
    }

    public function cancel(QueueEntry $entry, ?User $actor): void
    {
        $this->mutate($entry->getDoctor(), $entry->getQueueDate(), function () use ($entry, $actor): void {
            $this->transition($entry, QueueStatus::CANCELLED, QueueAction::CANCELLED, $actor);
        });
    }

    public function checkIn(QueueEntry $entry, User $actor): void
    {
        if (!$entry->getStatus()->isActive() || $entry->isCheckedIn()) {
            return;
        }

        $this->mutate($entry->getDoctor(), $entry->getQueueDate(), function () use ($entry, $actor): void {
            $entry->checkIn($this->clock->now());
            $this->record($entry, QueueAction::CHECKED_IN, $actor);
        });
    }

    public function setDoctorAvailability(Doctor $doctor, bool $available): void
    {
        $doctor->setIsAvailableToday($available);
        $this->em->flush();
        $this->events->dispatch(new QueueChangedEvent($doctor->getId()));
    }

    public function closeDay(\DateTimeImmutable $date): int
    {
        $closed = 0;
        $doctorIds = [];

        $this->em->wrapInTransaction(function () use ($date, &$closed, &$doctorIds): void {
            foreach ($this->entries->findWaitingForDate($date) as $entry) {
                $this->transition($entry, QueueStatus::CANCELLED, QueueAction::CANCELLED, null);
                $doctorIds[$entry->getDoctor()->getId()] = true;
                ++$closed;
            }
            $this->em->flush();
        });

        foreach (array_keys($doctorIds) as $doctorId) {
            $this->events->dispatch(new QueueChangedEvent($doctorId));
        }

        return $closed;
    }

    /**
     * @template T
     *
     * @param \Closure(array<int, PendingNotification>): T $change
     *
     * @return T
     */
    private function mutate(Doctor $doctor, \DateTimeImmutable $date, \Closure $change): mixed
    {
        $pending = [];

        $result = $this->em->wrapInTransaction(function () use ($doctor, $date, $change, &$pending) {
            $this->em->lock($doctor, LockMode::PESSIMISTIC_WRITE);
            $result = $change($pending);
            $this->em->flush();

            foreach ($this->flagApproaching($doctor, $date) as $notification) {
                $pending[] = $notification;
            }
            $this->em->flush();

            return $result;
        });

        foreach ($pending as $notification) {
            $this->dispatchNotification($notification);
        }

        $this->events->dispatch(new QueueChangedEvent($doctor->getId()));

        return $result;
    }

    /** @param array<int, PendingNotification> $pending */
    private function markCalled(QueueEntry $entry, User $actor, array &$pending): void
    {
        $this->transition($entry, QueueStatus::CALLED, QueueAction::CALLED, $actor);
        $entry->setCalledAt($this->clock->now());
        if (null !== $entry->getPatientPhone()) {
            $pending[] = new PendingNotification($entry, NotificationType::CALLED);
        }
    }

    private function nextWaiting(Doctor $doctor, \DateTimeImmutable $date): ?QueueEntry
    {
        $waiting = array_values(array_filter(
            $this->entries->findQueued($doctor, $date),
            static fn (QueueEntry $entry): bool => QueueStatus::WAITING === $entry->getStatus(),
        ));

        if ([] === $waiting) {
            return null;
        }

        if ($doctor->getClinic()->isPreferCheckedIn()) {
            foreach ($waiting as $entry) {
                if ($entry->isCheckedIn()) {
                    return $entry;
                }
            }
        }

        return $waiting[0];
    }

    private function createEntry(Doctor $doctor, \DateTimeImmutable $date, string $name, QueueSource $source): QueueEntry
    {
        $sequence = $this->entries->findNextSequence($doctor, $date);
        $token = \sprintf('%s%03d', $doctor->getTokenPrefix(), $sequence);

        $entry = new QueueEntry($doctor->getClinic(), $doctor, $date, $sequence, $token, $name, $source, $this->clock->now());
        $this->em->persist($entry);

        return $entry;
    }

    /** @param \Closure(list<QueueEntry>): int $indexFor */
    private function insertInLine(QueueEntry $entry, Doctor $doctor, \DateTimeImmutable $date, \Closure $indexFor): void
    {
        $line = array_values(array_filter(
            $this->entries->findQueued($doctor, $date),
            static fn (QueueEntry $candidate): bool => $candidate->getId() !== $entry->getId(),
        ));

        array_splice($line, $indexFor($line), 0, [$entry]);

        foreach ($line as $index => $ordered) {
            $ordered->setSortOrder($index + 1);
        }
    }

    /** @return \Closure(list<QueueEntry>): int */
    private function priorityIndexer(Doctor $doctor): \Closure
    {
        $clinic = $doctor->getClinic();

        return fn (array $line): int => $this->placer->priorityIndex($line, $clinic->getPriorityRule(), $clinic->getPriorityAfterN());
    }

    private function transition(QueueEntry $entry, QueueStatus $target, QueueAction $action, ?User $actor): void
    {
        $current = $entry->getStatus();
        if (!$current->canTransitionTo($target)) {
            throw new InvalidQueueTransitionException(\sprintf('Token %s cannot move from %s to %s.', $entry->getTokenNumber(), $current->label(), $target->label()));
        }

        $entry->setStatus($target);
        $this->record($entry, $action, $actor);
    }

    private function assertStatus(QueueEntry $entry, QueueStatus $expected, string $operation): void
    {
        if ($entry->getStatus() !== $expected) {
            throw new InvalidQueueTransitionException(\sprintf('Cannot %s token %s while it is %s.', $operation, $entry->getTokenNumber(), $entry->getStatus()->label()));
        }
    }

    private function record(QueueEntry $entry, QueueAction $action, ?User $actor): void
    {
        $this->em->persist(new QueueEvent($entry, $action, $actor, $this->clock->now()));
    }

    /** @return list<PendingNotification> */
    private function flagApproaching(Doctor $doctor, \DateTimeImmutable $date): array
    {
        $threshold = $doctor->getClinic()->getNotifyThreshold();
        $notifications = [];

        foreach ($this->entries->findQueued($doctor, $date) as $index => $entry) {
            if ($index + 1 > $threshold) {
                break;
            }

            if (QueueStatus::WAITING !== $entry->getStatus() || $entry->isApproachingNotified()) {
                continue;
            }

            $entry->markApproachingNotified();
            $notifications[] = new PendingNotification($entry, NotificationType::APPROACHING);
        }

        return $notifications;
    }

    private function dispatchNotification(PendingNotification $notification): void
    {
        try {
            $this->bus->dispatch(new SendQueueNotification($notification->entry->getId(), $notification->type));
        } catch (\Throwable $exception) {
            $this->logger->error('Could not queue patient notification.', [
                'queueEntryId' => $notification->entry->getId(),
                'type' => $notification->type->value,
                'exception' => $exception,
            ]);
        }
    }
}
