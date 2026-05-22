<?php

namespace App\Repository;

use App\Entity\Course;
use App\Entity\Lesson;
use App\Entity\Quiz;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;

/**
 * @extends ServiceEntityRepository<Quiz>
 */
class QuizRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, Quiz::class);
    }

    public function findOneByCourse(Course $course): ?Quiz
    {
        return $this->findOneBy(['course' => $course]);
    }

    public function findOneByLesson(Lesson $lesson): ?Quiz
    {
        return $this->findOneBy(['lesson' => $lesson]);
    }
}
