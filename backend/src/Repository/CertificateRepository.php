<?php

namespace App\Repository;

use App\Entity\Certificate;
use App\Entity\Course;
use App\Entity\User;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;

/**
 * @extends ServiceEntityRepository<Certificate>
 */
class CertificateRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, Certificate::class);
    }

    public function findOneByUserAndCourse(User $user, Course $course): ?Certificate
    {
        return $this->findOneBy(['user' => $user, 'course' => $course]);
    }

    /**
     * @return list<Certificate>
     */
    public function findForUser(User $user): array
    {
        return $this->findBy(['user' => $user], ['issuedAt' => 'DESC']);
    }
}
