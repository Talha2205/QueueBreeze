<?php

namespace App\Repository;

use App\Entity\Clinic;
use App\Entity\User;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;

/** @extends ServiceEntityRepository<User> */
class UserRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, User::class);
    }

    /** @return list<User> */
    public function findForClinic(Clinic $clinic): array
    {
        return $this->findBy(['clinic' => $clinic], ['name' => 'ASC']);
    }
}
