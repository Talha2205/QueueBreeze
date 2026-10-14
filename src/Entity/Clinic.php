<?php

namespace App\Entity;

use App\Enum\NotificationProvider;
use App\Enum\PriorityRule;
use App\Enum\RecallPlacement;
use App\Repository\ClinicRepository;
use Doctrine\DBAL\Types\Types;
use Doctrine\ORM\Mapping as ORM;

#[ORM\Entity(repositoryClass: ClinicRepository::class)]
#[ORM\Table(name: 'clinic')]
class Clinic
{
    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column]
    private ?int $id = null;

    #[ORM\Column(length: 150)]
    private string $name;

    #[ORM\Column(length: 150, unique: true)]
    private string $slug;

    #[ORM\Column(length: 255, nullable: true)]
    private ?string $address = null;

    #[ORM\Column(length: 30, nullable: true)]
    private ?string $phone = null;

    #[ORM\Column]
    private bool $isActive = true;

    #[ORM\Column(options: ['default' => 3])]
    private int $notifyThreshold = 3;

    #[ORM\Column(enumType: PriorityRule::class, options: ['default' => 'NEXT_IN_LINE'])]
    private PriorityRule $priorityRule = PriorityRule::NEXT_IN_LINE;

    #[ORM\Column(options: ['default' => 3])]
    private int $priorityAfterN = 3;

    #[ORM\Column(enumType: RecallPlacement::class, options: ['default' => 'FRONT'])]
    private RecallPlacement $recallPlacement = RecallPlacement::FRONT;

    #[ORM\Column(options: ['default' => true])]
    private bool $preferCheckedIn = true;

    #[ORM\Column(options: ['default' => 25])]
    private int $waitRangeBufferPercent = 25;

    #[ORM\Column(enumType: NotificationProvider::class, options: ['default' => 'log'])]
    private NotificationProvider $notificationProvider = NotificationProvider::LOG;

    #[ORM\Column(type: Types::DATETIME_IMMUTABLE)]
    private \DateTimeImmutable $createdAt;

    public function __construct(string $name, string $slug)
    {
        $this->name = $name;
        $this->slug = $slug;
        $this->createdAt = new \DateTimeImmutable();
    }

    public function getId(): ?int
    {
        return $this->id;
    }

    public function getName(): string
    {
        return $this->name;
    }

    public function setName(string $name): void
    {
        $this->name = $name;
    }

    public function getSlug(): string
    {
        return $this->slug;
    }

    public function getAddress(): ?string
    {
        return $this->address;
    }

    public function setAddress(?string $address): void
    {
        $this->address = $address;
    }

    public function getPhone(): ?string
    {
        return $this->phone;
    }

    public function setPhone(?string $phone): void
    {
        $this->phone = $phone;
    }

    public function isActive(): bool
    {
        return $this->isActive;
    }

    public function setIsActive(bool $isActive): void
    {
        $this->isActive = $isActive;
    }

    public function getNotifyThreshold(): int
    {
        return $this->notifyThreshold;
    }

    public function setNotifyThreshold(int $notifyThreshold): void
    {
        $this->notifyThreshold = $notifyThreshold;
    }

    public function getPriorityRule(): PriorityRule
    {
        return $this->priorityRule;
    }

    public function setPriorityRule(PriorityRule $priorityRule): void
    {
        $this->priorityRule = $priorityRule;
    }

    public function getPriorityAfterN(): int
    {
        return $this->priorityAfterN;
    }

    public function setPriorityAfterN(int $priorityAfterN): void
    {
        $this->priorityAfterN = $priorityAfterN;
    }

    public function getRecallPlacement(): RecallPlacement
    {
        return $this->recallPlacement;
    }

    public function setRecallPlacement(RecallPlacement $recallPlacement): void
    {
        $this->recallPlacement = $recallPlacement;
    }

    public function isPreferCheckedIn(): bool
    {
        return $this->preferCheckedIn;
    }

    public function setPreferCheckedIn(bool $preferCheckedIn): void
    {
        $this->preferCheckedIn = $preferCheckedIn;
    }

    public function getWaitRangeBufferPercent(): int
    {
        return $this->waitRangeBufferPercent;
    }

    public function setWaitRangeBufferPercent(int $waitRangeBufferPercent): void
    {
        $this->waitRangeBufferPercent = $waitRangeBufferPercent;
    }

    public function getNotificationProvider(): NotificationProvider
    {
        return $this->notificationProvider;
    }

    public function setNotificationProvider(NotificationProvider $notificationProvider): void
    {
        $this->notificationProvider = $notificationProvider;
    }

    public function getCreatedAt(): \DateTimeImmutable
    {
        return $this->createdAt;
    }
}
