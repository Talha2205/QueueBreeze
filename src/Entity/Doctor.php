<?php

namespace App\Entity;

use App\Repository\DoctorRepository;
use Doctrine\ORM\Mapping as ORM;

#[ORM\Entity(repositoryClass: DoctorRepository::class)]
#[ORM\Table(name: 'doctor')]
#[ORM\UniqueConstraint(name: 'uniq_doctor_clinic_prefix', columns: ['clinic_id', 'token_prefix'])]
class Doctor
{
    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column]
    private ?int $id = null;

    #[ORM\ManyToOne(targetEntity: Clinic::class)]
    #[ORM\JoinColumn(nullable: false)]
    private Clinic $clinic;

    #[ORM\Column(length: 120)]
    private string $name;

    #[ORM\Column(length: 120, nullable: true)]
    private ?string $specialty = null;

    #[ORM\Column(length: 1)]
    private string $tokenPrefix;

    #[ORM\Column(options: ['default' => 10])]
    private int $avgConsultationMinutes = 10;

    #[ORM\Column(options: ['default' => true])]
    private bool $isAvailableToday = true;

    #[ORM\Column(options: ['default' => true])]
    private bool $isActive = true;

    public function __construct(Clinic $clinic, string $name, string $tokenPrefix)
    {
        $this->clinic = $clinic;
        $this->name = $name;
        $this->tokenPrefix = strtoupper($tokenPrefix);
    }

    public function getId(): ?int
    {
        return $this->id;
    }

    public function getClinic(): Clinic
    {
        return $this->clinic;
    }

    public function getName(): string
    {
        return $this->name;
    }

    public function setName(string $name): void
    {
        $this->name = $name;
    }

    public function getSpecialty(): ?string
    {
        return $this->specialty;
    }

    public function setSpecialty(?string $specialty): void
    {
        $this->specialty = $specialty;
    }

    public function getTokenPrefix(): string
    {
        return $this->tokenPrefix;
    }

    public function setTokenPrefix(string $tokenPrefix): void
    {
        $this->tokenPrefix = strtoupper($tokenPrefix);
    }

    public function getAvgConsultationMinutes(): int
    {
        return $this->avgConsultationMinutes;
    }

    public function setAvgConsultationMinutes(int $avgConsultationMinutes): void
    {
        $this->avgConsultationMinutes = $avgConsultationMinutes;
    }

    public function isAvailableToday(): bool
    {
        return $this->isAvailableToday;
    }

    public function setIsAvailableToday(bool $isAvailableToday): void
    {
        $this->isAvailableToday = $isAvailableToday;
    }

    public function isActive(): bool
    {
        return $this->isActive;
    }

    public function setIsActive(bool $isActive): void
    {
        $this->isActive = $isActive;
    }
}
