<?php

namespace App\Repository;

use App\Entity\Lesson;
use App\Entity\LessonCompletion;
use App\Entity\User;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;

/**
 * @extends ServiceEntityRepository<LessonCompletion>
 */
class LessonCompletionRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, LessonCompletion::class);
    }

    public function findOneByUserAndLesson(User $user, Lesson $lesson): ?LessonCompletion
    {
        return $this->findOneBy(['user' => $user, 'lesson' => $lesson]);
    }

    /**
     * IDs of the lessons (among the given ones) the user has completed.
     *
     * @param list<int> $lessonIds
     *
     * @return list<int>
     */
    public function findCompletedLessonIds(User $user, array $lessonIds): array
    {
        if ([] === $lessonIds) {
            return [];
        }

        $rows = $this->createQueryBuilder('lc')
            ->select('IDENTITY(lc.lesson) AS lessonId')
            ->where('lc.user = :user')
            ->andWhere('lc.lesson IN (:ids)')
            ->setParameter('user', $user)
            ->setParameter('ids', $lessonIds)
            ->getQuery()
            ->getScalarResult();

        return array_map(static fn (array $row): int => (int) $row['lessonId'], $rows);
    }

    /**
     * Completed-lesson counts per course for one user, keyed by course id.
     *
     * @return array<int, int>
     */
    public function countCompletedPerCourse(User $user): array
    {
        $rows = $this->createQueryBuilder('lc')
            ->select('IDENTITY(l.course) AS courseId', 'COUNT(lc.id) AS cnt')
            ->join('lc.lesson', 'l')
            ->where('lc.user = :user')
            ->setParameter('user', $user)
            ->groupBy('courseId')
            ->getQuery()
            ->getScalarResult();

        $counts = [];
        foreach ($rows as $row) {
            $counts[(int) $row['courseId']] = (int) $row['cnt'];
        }

        return $counts;
    }
}
