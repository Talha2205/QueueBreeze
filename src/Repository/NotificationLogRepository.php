<?php

namespace App\Repository;

use App\Entity\NotificationLog;
use App\Entity\QueueEntry;
use App\Enum\NotificationStatus;
use App\Enum\NotificationType;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;

/** @extends ServiceEntityRepository<NotificationLog> */
class NotificationLogRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, NotificationLog::class);
    }

    public function hasSent(QueueEntry $entry, NotificationType $type): bool
    {
        return null !== $this->findOneBy([
            'queueEntry' => $entry,
            'type' => $type,
            'status' => NotificationStatus::SENT,
        ]);
    }
}
