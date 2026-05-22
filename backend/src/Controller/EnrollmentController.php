<?php

namespace App\Controller;

use App\Entity\Course;
use App\Entity\Enrollment;
use App\Entity\Lesson;
use App\Entity\LessonCompletion;
use App\Entity\User;
use App\Repository\EnrollmentRepository;
use App\Repository\LessonCompletionRepository;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Security\Http\Attribute\CurrentUser;

/**
 * Self-service course enrolment and lesson-progress tracking for the
 * currently authenticated user.
 */
final class EnrollmentController extends AbstractController
{
    public function __construct(
        private readonly EntityManagerInterface $entityManager,
        private readonly EnrollmentRepository $enrollments,
        private readonly LessonCompletionRepository $completions,
    ) {
    }

    #[Route('/api/courses/{id<\d+>}/enroll', name: 'api_course_enroll', methods: ['POST'])]
    public function enroll(Course $course, #[CurrentUser] User $user): JsonResponse
    {
        if (null === $this->enrollments->findOneByUserAndCourse($user, $course)) {
            $this->entityManager->persist(new Enrollment($user, $course));
            $this->entityManager->flush();
        }

        return $this->json(['status' => 'enrolled']);
    }

    #[Route('/api/courses/{id<\d+>}/enroll', name: 'api_course_unenroll', methods: ['DELETE'])]
    public function unenroll(Course $course, #[CurrentUser] User $user): JsonResponse
    {
        $enrollment = $this->enrollments->findOneByUserAndCourse($user, $course);
        if (null !== $enrollment) {
            $this->entityManager->remove($enrollment);
            $this->entityManager->flush();
        }

        return $this->json(['status' => 'unenrolled']);
    }

    #[Route('/api/lessons/{id<\d+>}/complete', name: 'api_lesson_complete', methods: ['POST'])]
    public function completeLesson(Lesson $lesson, #[CurrentUser] User $user): JsonResponse
    {
        $course = $lesson->getCourse();
        if (null === $course || null === $this->enrollments->findOneByUserAndCourse($user, $course)) {
            return $this->json(
                ['error' => 'Előbb iratkozz be a kurzusra.'],
                JsonResponse::HTTP_UNPROCESSABLE_ENTITY,
            );
        }

        if (null === $this->completions->findOneByUserAndLesson($user, $lesson)) {
            $this->entityManager->persist(new LessonCompletion($user, $lesson));
            $this->entityManager->flush();
        }

        return $this->json(['status' => 'completed']);
    }

    #[Route('/api/lessons/{id<\d+>}/complete', name: 'api_lesson_uncomplete', methods: ['DELETE'])]
    public function uncompleteLesson(Lesson $lesson, #[CurrentUser] User $user): JsonResponse
    {
        $completion = $this->completions->findOneByUserAndLesson($user, $lesson);
        if (null !== $completion) {
            $this->entityManager->remove($completion);
            $this->entityManager->flush();
        }

        return $this->json(['status' => 'uncompleted']);
    }
}
