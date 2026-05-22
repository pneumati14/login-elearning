<?php

namespace App\Repository;

use App\Entity\Quiz;
use App\Entity\QuizAttempt;
use App\Entity\User;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;

/**
 * @extends ServiceEntityRepository<QuizAttempt>
 */
class QuizAttemptRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, QuizAttempt::class);
    }

    /**
     * IDs of the quizzes the user has at least one passing attempt on.
     *
     * @return list<int>
     */
    public function passedQuizIdsForUser(User $user): array
    {
        $rows = $this->createQueryBuilder('a')
            ->select('DISTINCT IDENTITY(a.quiz) AS quizId')
            ->where('a.user = :user')
            ->andWhere('a.passed = :passed')
            ->setParameter('user', $user)
            ->setParameter('passed', true)
            ->getQuery()
            ->getScalarResult();

        return array_map(static fn (array $row): int => (int) $row['quizId'], $rows);
    }

    public function findLatestForUserAndQuiz(User $user, Quiz $quiz): ?QuizAttempt
    {
        return $this->createQueryBuilder('a')
            ->where('a.user = :user')
            ->andWhere('a.quiz = :quiz')
            ->setParameter('user', $user)
            ->setParameter('quiz', $quiz)
            ->orderBy('a.completedAt', 'DESC')
            ->setMaxResults(1)
            ->getQuery()
            ->getOneOrNullResult();
    }
}
