<?php

namespace App\Entity;

use App\Enum\QueueAction;
use App\Repository\QueueEventRepository;
use Doctrine\DBAL\Types\Types;
use Doctrine\ORM\Mapping as ORM;

#[ORM\Entity(repositoryClass: QueueEventRepository::class)]
#[ORM\Table(name: 'queue_event')]
class QueueEvent
{
    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column]
    private ?int $id = null;

    #[ORM\ManyToOne(targetEntity: QueueEntry::class)]
    #[ORM\JoinColumn(nullable: false, onDelete: 'CASCADE')]
    private QueueEntry $queueEntry;

    #[ORM\Column(length: 30, enumType: QueueAction::class)]
    private QueueAction $action;

    #[ORM\ManyToOne(targetEntity: User::class)]
    #[ORM\JoinColumn(nullable: true)]
    private ?User $performedBy;

    #[ORM\Column(type: Types::DATETIME_IMMUTABLE)]
    private \DateTimeImmutable $createdAt;

    public function __construct(QueueEntry $queueEntry, QueueAction $action, ?User $performedBy, \DateTimeImmutable $createdAt)
    {
        $this->queueEntry = $queueEntry;
        $this->action = $action;
        $this->performedBy = $performedBy;
        $this->createdAt = $createdAt;
    }

    public function getId(): ?int
    {
        return $this->id;
    }

    public function getQueueEntry(): QueueEntry
    {
        return $this->queueEntry;
    }

    public function getAction(): QueueAction
    {
        return $this->action;
    }

    public function getPerformedBy(): ?User
    {
        return $this->performedBy;
    }

    public function getCreatedAt(): \DateTimeImmutable
    {
        return $this->createdAt;
    }
}
