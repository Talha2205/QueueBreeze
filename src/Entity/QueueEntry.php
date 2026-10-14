<?php

namespace App\Entity;

use App\Enum\NotificationChannel;
use App\Enum\QueueSource;
use App\Enum\QueueStatus;
use App\Repository\QueueEntryRepository;
use Doctrine\DBAL\Types\Types;
use Doctrine\ORM\Mapping as ORM;

#[ORM\Entity(repositoryClass: QueueEntryRepository::class)]
#[ORM\Table(name: 'queue_entry')]
#[ORM\UniqueConstraint(name: 'uniq_token_per_doctor_day', columns: ['doctor_id', 'queue_date', 'sequence'])]
#[ORM\Index(name: 'idx_queue_lookup', columns: ['doctor_id', 'queue_date', 'status'])]
class QueueEntry
{
    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column]
    private ?int $id = null;

    #[ORM\Column(length: 40, unique: true)]
    private string $publicId;

    #[ORM\ManyToOne(targetEntity: Clinic::class)]
    #[ORM\JoinColumn(nullable: false)]
    private Clinic $clinic;

    #[ORM\ManyToOne(targetEntity: Doctor::class)]
    #[ORM\JoinColumn(nullable: false)]
    private Doctor $doctor;

    #[ORM\Column(length: 8)]
    private string $tokenNumber;

    #[ORM\Column]
    private int $sequence;

    #[ORM\Column(type: Types::DATE_IMMUTABLE)]
    private \DateTimeImmutable $queueDate;

    #[ORM\Column(length: 120)]
    private string $patientName;

    #[ORM\Column(length: 20, nullable: true)]
    private ?string $patientPhone = null;

    #[ORM\Column(length: 20, nullable: true, enumType: NotificationChannel::class)]
    private ?NotificationChannel $notificationChannel = null;

    #[ORM\Column(length: 20, enumType: QueueSource::class)]
    private QueueSource $source;

    #[ORM\Column(options: ['default' => false])]
    private bool $isPriority = false;

    #[ORM\Column(length: 255, nullable: true)]
    private ?string $priorityReason = null;

    #[ORM\ManyToOne(targetEntity: User::class)]
    #[ORM\JoinColumn(nullable: true)]
    private ?User $priorityMarkedBy = null;

    #[ORM\Column(length: 20, enumType: QueueStatus::class)]
    private QueueStatus $status = QueueStatus::WAITING;

    #[ORM\Column(options: ['default' => false])]
    private bool $checkedIn = false;

    #[ORM\Column(type: Types::DATETIME_IMMUTABLE, nullable: true)]
    private ?\DateTimeImmutable $checkedInAt = null;

    #[ORM\Column(type: Types::DATETIME_IMMUTABLE)]
    private \DateTimeImmutable $joinedAt;

    #[ORM\Column(type: Types::DATETIME_IMMUTABLE, nullable: true)]
    private ?\DateTimeImmutable $calledAt = null;

    #[ORM\Column(type: Types::DATETIME_IMMUTABLE, nullable: true)]
    private ?\DateTimeImmutable $startedAt = null;

    #[ORM\Column(type: Types::DATETIME_IMMUTABLE, nullable: true)]
    private ?\DateTimeImmutable $completedAt = null;

    #[ORM\Column(type: Types::DATETIME_IMMUTABLE, nullable: true)]
    private ?\DateTimeImmutable $skippedAt = null;

    #[ORM\Column(options: ['default' => false])]
    private bool $approachingNotified = false;

    #[ORM\Column(options: ['default' => 0])]
    private int $sortOrder = 0;

    public function __construct(
        Clinic $clinic,
        Doctor $doctor,
        \DateTimeImmutable $queueDate,
        int $sequence,
        string $tokenNumber,
        string $patientName,
        QueueSource $source,
        \DateTimeImmutable $joinedAt,
    ) {
        $this->publicId = bin2hex(random_bytes(20));
        $this->clinic = $clinic;
        $this->doctor = $doctor;
        $this->queueDate = $queueDate;
        $this->sequence = $sequence;
        $this->tokenNumber = $tokenNumber;
        $this->patientName = $patientName;
        $this->source = $source;
        $this->joinedAt = $joinedAt;
    }

    public function getId(): ?int
    {
        return $this->id;
    }

    public function getPublicId(): string
    {
        return $this->publicId;
    }

    public function getClinic(): Clinic
    {
        return $this->clinic;
    }

    public function getDoctor(): Doctor
    {
        return $this->doctor;
    }

    public function getTokenNumber(): string
    {
        return $this->tokenNumber;
    }

    public function getSequence(): int
    {
        return $this->sequence;
    }

    public function getQueueDate(): \DateTimeImmutable
    {
        return $this->queueDate;
    }

    public function getPatientName(): string
    {
        return $this->patientName;
    }

    public function setPatientName(string $patientName): void
    {
        $this->patientName = $patientName;
    }

    public function getPatientPhone(): ?string
    {
        return $this->patientPhone;
    }

    public function getNotificationChannel(): ?NotificationChannel
    {
        return $this->notificationChannel;
    }

    public function setContact(?string $phone, ?NotificationChannel $channel): void
    {
        $this->patientPhone = $phone;
        $this->notificationChannel = $channel;
    }

    public function getSource(): QueueSource
    {
        return $this->source;
    }

    public function isPriority(): bool
    {
        return $this->isPriority;
    }

    public function markPriority(string $reason, User $markedBy): void
    {
        $this->isPriority = true;
        $this->priorityReason = $reason;
        $this->priorityMarkedBy = $markedBy;
    }

    public function getPriorityReason(): ?string
    {
        return $this->priorityReason;
    }

    public function getPriorityMarkedBy(): ?User
    {
        return $this->priorityMarkedBy;
    }

    public function getStatus(): QueueStatus
    {
        return $this->status;
    }

    public function setStatus(QueueStatus $status): void
    {
        $this->status = $status;
    }

    public function isCheckedIn(): bool
    {
        return $this->checkedIn;
    }

    public function getCheckedInAt(): ?\DateTimeImmutable
    {
        return $this->checkedInAt;
    }

    public function checkIn(\DateTimeImmutable $at): void
    {
        $this->checkedIn = true;
        $this->checkedInAt = $at;
    }

    public function getJoinedAt(): \DateTimeImmutable
    {
        return $this->joinedAt;
    }

    public function getCalledAt(): ?\DateTimeImmutable
    {
        return $this->calledAt;
    }

    public function setCalledAt(?\DateTimeImmutable $calledAt): void
    {
        $this->calledAt = $calledAt;
    }

    public function getStartedAt(): ?\DateTimeImmutable
    {
        return $this->startedAt;
    }

    public function setStartedAt(?\DateTimeImmutable $startedAt): void
    {
        $this->startedAt = $startedAt;
    }

    public function getCompletedAt(): ?\DateTimeImmutable
    {
        return $this->completedAt;
    }

    public function setCompletedAt(?\DateTimeImmutable $completedAt): void
    {
        $this->completedAt = $completedAt;
    }

    public function getSkippedAt(): ?\DateTimeImmutable
    {
        return $this->skippedAt;
    }

    public function setSkippedAt(?\DateTimeImmutable $skippedAt): void
    {
        $this->skippedAt = $skippedAt;
    }

    public function isApproachingNotified(): bool
    {
        return $this->approachingNotified;
    }

    public function markApproachingNotified(): void
    {
        $this->approachingNotified = true;
    }

    public function getSortOrder(): int
    {
        return $this->sortOrder;
    }

    public function setSortOrder(int $sortOrder): void
    {
        $this->sortOrder = $sortOrder;
    }
}
