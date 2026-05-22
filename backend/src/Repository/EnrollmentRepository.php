<?php

namespace App\Repository;

use App\Entity\Course;
use App\Entity\Enrollment;
use App\Entity\User;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;

/**
 * @extends ServiceEntityRepository<Enrollment>
 */
class EnrollmentRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, Enrollment::class);
    }

    public function findOneByUserAndCourse(User $user, Course $course): ?Enrollment
    {
        return $this->findOneBy(['user' => $user, 'course' => $course]);
    }

    /**
     * IDs of the courses the user is enrolled in.
     *
     * @return list<int>
     */
    public function findCourseIdsForUser(User $user): array
    {
        $rows = $this->createQueryBuilder('e')
            ->select('IDENTITY(e.course) AS courseId')
            ->where('e.user = :user')
            ->setParameter('user', $user)
            ->getQuery()
            ->getScalarResult();

        return array_map(static fn (array $row): int => (int) $row['courseId'], $rows);
    }
}
