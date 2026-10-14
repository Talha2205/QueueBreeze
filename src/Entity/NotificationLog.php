<?php

namespace App\Entity;

use App\Enum\NotificationChannel;
use App\Enum\NotificationStatus;
use App\Enum\NotificationType;
use App\Repository\NotificationLogRepository;
use Doctrine\DBAL\Types\Types;
use Doctrine\ORM\Mapping as ORM;

#[ORM\Entity(repositoryClass: NotificationLogRepository::class)]
#[ORM\Table(name: 'notification_log')]
class NotificationLog
{
    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column]
    private ?int $id = null;

    #[ORM\ManyToOne(targetEntity: QueueEntry::class)]
    #[ORM\JoinColumn(nullable: false, onDelete: 'CASCADE')]
    private QueueEntry $queueEntry;

    #[ORM\Column(length: 20, enumType: NotificationChannel::class)]
    private NotificationChannel $channel;

    #[ORM\Column(length: 20, enumType: NotificationType::class)]
    private NotificationType $type;

    #[ORM\Column(length: 20, enumType: NotificationStatus::class)]
    private NotificationStatus $status;

    #[ORM\Column(type: Types::TEXT, nullable: true)]
    private ?string $providerResponse = null;

    #[ORM\Column(type: Types::DATETIME_IMMUTABLE)]
    private \DateTimeImmutable $sentAt;

    public function __construct(
        QueueEntry $queueEntry,
        NotificationChannel $channel,
        NotificationType $type,
        NotificationStatus $status,
        ?string $providerResponse,
        \DateTimeImmutable $sentAt,
    ) {
        $this->queueEntry = $queueEntry;
        $this->channel = $channel;
        $this->type = $type;
        $this->status = $status;
        $this->providerResponse = $providerResponse;
        $this->sentAt = $sentAt;
    }

    public function getId(): ?int
    {
        return $this->id;
    }

    public function getQueueEntry(): QueueEntry
    {
        return $this->queueEntry;
    }

    public function getChannel(): NotificationChannel
    {
        return $this->channel;
    }

    public function getType(): NotificationType
    {
        return $this->type;
    }

    public function getStatus(): NotificationStatus
    {
        return $this->status;
    }

    public function getProviderResponse(): ?string
    {
        return $this->providerResponse;
    }

    public function getSentAt(): \DateTimeImmutable
    {
        return $this->sentAt;
    }
}
