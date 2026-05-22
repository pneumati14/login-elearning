<?php

namespace App\Service;

use App\Entity\Certificate;
use App\Entity\Course;
use App\Entity\Lesson;
use App\Entity\User;
use App\Repository\CertificateRepository;
use App\Repository\LessonCompletionRepository;
use App\Repository\QuizAttemptRepository;
use App\Repository\QuizRepository;
use Doctrine\ORM\EntityManagerInterface;

/**
 * Awards course-completion certificates. A certificate is issued once
 * the user has completed every lesson of a course and — if the course
 * has a quiz — passed that quiz.
 */
final class CertificateService
{
    public function __construct(
        private readonly EntityManagerInterface $entityManager,
        private readonly CertificateRepository $certificates,
        private readonly LessonCompletionRepository $completions,
        private readonly QuizRepository $quizzes,
        private readonly QuizAttemptRepository $quizAttempts,
    ) {
    }

    /**
     * Issues the certificate if the user now qualifies and does not yet
     * hold one. Returns the certificate (existing or new), or null when
     * the course is not yet completed.
     */
    public function syncForCourse(User $user, Course $course): ?Certificate
    {
        $existing = $this->certificates->findOneByUserAndCourse($user, $course);
        if (null !== $existing) {
            return $existing;
        }

        if (!$this->isCourseCompleted($user, $course)) {
            return null;
        }

        $certificate = new Certificate($user, $course, $this->generateCode());
        $this->entityManager->persist($certificate);
        $this->entityManager->flush();

        return $certificate;
    }

    private function isCourseCompleted(User $user, Course $course): bool
    {
        $lessons = $course->getLessons()->toArray();
        if ([] === $lessons) {
            return false;
        }

        $lessonIds = array_map(static fn (Lesson $lesson): int => (int) $lesson->getId(), $lessons);
        $completed = $this->completions->findCompletedLessonIds($user, $lessonIds);
        if (\count($completed) < \count($lessonIds)) {
            return false;
        }

        $quiz = $this->quizzes->findOneByCourse($course);
        if (null !== $quiz
            && !\in_array($quiz->getId(), $this->quizAttempts->passedQuizIdsForUser($user), true)) {
            return false;
        }

        return true;
    }

    private function generateCode(): string
    {
        return strtoupper(bin2hex(random_bytes(4)));
    }
}
