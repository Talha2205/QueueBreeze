<?php

namespace App\Repository;

use App\Entity\Clinic;
use App\Entity\Doctor;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;

/** @extends ServiceEntityRepository<Doctor> */
class DoctorRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, Doctor::class);
    }

    /** @return list<Doctor> */
    public function findForClinic(Clinic $clinic, bool $activeOnly = false): array
    {
        $criteria = ['clinic' => $clinic];
        if ($activeOnly) {
            $criteria['isActive'] = true;
        }

        return $this->findBy($criteria, ['name' => 'ASC']);
    }

    public function findOneByClinicAndPrefix(Clinic $clinic, string $prefix): ?Doctor
    {
        return $this->findOneBy(['clinic' => $clinic, 'tokenPrefix' => strtoupper($prefix)]);
    }
}
