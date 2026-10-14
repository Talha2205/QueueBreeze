<?php

namespace App\MessageHandler;

use App\Entity\NotificationLog;
use App\Enum\NotificationStatus;
use App\Message\SendQueueNotification;
use App\Repository\NotificationLogRepository;
use App\Repository\QueueEntryRepository;
use App\Service\Notification\NotificationMessageFactory;
use App\Service\Notification\NotificationSender;
use Doctrine\ORM\EntityManagerInterface;
use Psr\Clock\ClockInterface;
use Symfony\Component\Messenger\Attribute\AsMessageHandler;

#[AsMessageHandler]
final class SendQueueNotificationHandler
{
    public function __construct(
        private readonly QueueEntryRepository $entries,
        private readonly NotificationLogRepository $logs,
        private readonly NotificationMessageFactory $messages,
        private readonly NotificationSender $sender,
        private readonly EntityManagerInterface $em,
        private readonly ClockInterface $clock,
    ) {
    }

    public function __invoke(SendQueueNotification $message): void
    {
        $entry = $this->entries->find($message->queueEntryId);
        if (null === $entry || null === $entry->getPatientPhone() || null === $entry->getNotificationChannel()) {
            return;
        }

        if ($this->logs->hasSent($entry, $message->type)) {
            return;
        }

        $channel = $entry->getNotificationChannel();
        $provider = $entry->getClinic()->getNotificationProvider();

        try {
            $response = $this->sender->send(
                $entry->getPatientPhone(),
                $channel,
                $provider,
                $this->messages->build($entry, $message->type),
            );
            $status = NotificationStatus::SENT;
        } catch (\Throwable $exception) {
            $response = $exception->getMessage();
            $status = NotificationStatus::FAILED;
        }

        $this->em->persist(new NotificationLog($entry, $channel, $message->type, $status, $response, $this->clock->now()));
        $this->em->flush();
    }
}
