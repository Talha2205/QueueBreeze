<?php

namespace App\Repository;

use App\Entity\Clinic;
use App\Entity\Doctor;
use App\Entity\QueueEntry;
use App\Enum\QueueStatus;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;

/** @extends ServiceEntityRepository<QueueEntry> */
class QueueEntryRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, QueueEntry::class);
    }

    /** @return list<QueueEntry> WAITING and CALLED entries, in queue order */
    public function findQueued(Doctor $doctor, \DateTimeImmutable $date): array
    {
        return $this->createQueryBuilder('e')
            ->andWhere('e.doctor = :doctor')
            ->andWhere('e.queueDate = :date')
            ->andWhere('e.status IN (:statuses)')
            ->setParameter('doctor', $doctor)
            ->setParameter('date', $date, 'date_immutable')
            ->setParameter('statuses', [QueueStatus::WAITING, QueueStatus::CALLED])
            ->orderBy('e.sortOrder', 'ASC')
            ->addOrderBy('e.id', 'ASC')
            ->getQuery()
            ->getResult();
    }

    /** @return list<QueueEntry> */
    public function findForDoctorOnDate(Doctor $doctor, \DateTimeImmutable $date): array
    {
        return $this->createQueryBuilder('e')
            ->andWhere('e.doctor = :doctor')
            ->andWhere('e.queueDate = :date')
            ->setParameter('doctor', $doctor)
            ->setParameter('date', $date, 'date_immutable')
            ->orderBy('e.sortOrder', 'ASC')
            ->addOrderBy('e.id', 'ASC')
            ->getQuery()
            ->getResult();
    }

    /** @return list<QueueEntry> */
    public function findForClinicOnDate(Clinic $clinic, \DateTimeImmutable $date): array
    {
        return $this->createQueryBuilder('e')
            ->andWhere('e.clinic = :clinic')
            ->andWhere('e.queueDate = :date')
            ->setParameter('clinic', $clinic)
            ->setParameter('date', $date, 'date_immutable')
            ->orderBy('e.id', 'ASC')
            ->getQuery()
            ->getResult();
    }

    public function findInProgress(Doctor $doctor, \DateTimeImmutable $date): ?QueueEntry
    {
        return $this->findOneBy([
            'doctor' => $doctor,
            'queueDate' => $date,
            'status' => QueueStatus::IN_PROGRESS,
        ]);
    }

    public function findActiveForPhone(Doctor $doctor, \DateTimeImmutable $date, string $phone): ?QueueEntry
    {
        return $this->createQueryBuilder('e')
            ->andWhere('e.doctor = :doctor')
            ->andWhere('e.queueDate = :date')
            ->andWhere('e.patientPhone = :phone')
            ->andWhere('e.status IN (:statuses)')
            ->setParameter('doctor', $doctor)
            ->setParameter('date', $date, 'date_immutable')
            ->setParameter('phone', $phone)
            ->setParameter('statuses', [QueueStatus::WAITING, QueueStatus::CALLED, QueueStatus::IN_PROGRESS, QueueStatus::SKIPPED])
            ->setMaxResults(1)
            ->getQuery()
            ->getOneOrNullResult();
    }

    public function findByTokenOnDate(Clinic $clinic, \DateTimeImmutable $date, string $tokenNumber): ?QueueEntry
    {
        return $this->findOneBy([
            'clinic' => $clinic,
            'queueDate' => $date,
            'tokenNumber' => strtoupper($tokenNumber),
        ]);
    }

    public function findNextSequence(Doctor $doctor, \DateTimeImmutable $date): int
    {
        $max = $this->createQueryBuilder('e')
            ->select('MAX(e.sequence)')
            ->andWhere('e.doctor = :doctor')
            ->andWhere('e.queueDate = :date')
            ->setParameter('doctor', $doctor)
            ->setParameter('date', $date, 'date_immutable')
            ->getQuery()
            ->getSingleScalarResult();

        return ((int) $max) + 1;
    }

    /** @return list<QueueEntry> */
    public function findWaitingForDate(\DateTimeImmutable $date): array
    {
        return $this->findBy(['queueDate' => $date, 'status' => QueueStatus::WAITING]);
    }
}
