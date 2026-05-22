<?php

namespace App\Controller;

use App\Entity\Course;
use App\Entity\Lesson;
use App\Entity\Quiz;
use App\Entity\User;
use App\Repository\CourseRepository;
use App\Repository\EnrollmentRepository;
use App\Repository\LessonCompletionRepository;
use App\Repository\QuizAttemptRepository;
use App\Repository\QuizRepository;
use App\Service\CertificateService;
use App\Service\MediaStorage;
use Symfony\Bridge\Doctrine\Attribute\MapEntity;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\BinaryFileResponse;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Security\Http\Attribute\CurrentUser;

#[Route('/api/courses', name: 'api_courses_')]
final class CourseController extends AbstractController
{
    public function __construct(
        private readonly EnrollmentRepository $enrollments,
        private readonly LessonCompletionRepository $completions,
        private readonly QuizRepository $quizzes,
        private readonly QuizAttemptRepository $quizAttempts,
        private readonly CertificateService $certificates,
        private readonly MediaStorage $media,
    ) {
    }

    #[Route('', name: 'list', methods: ['GET'])]
    public function list(CourseRepository $courses, #[CurrentUser] User $user): JsonResponse
    {
        $enrolledCourseIds = array_flip($this->enrollments->findCourseIdsForUser($user));
        $completedPerCourse = $this->completions->countCompletedPerCourse($user);

        $data = array_map(
            static function (Course $course) use ($enrolledCourseIds, $completedPerCourse): array {
                $id = $course->getId();

                return [
                    'id' => $id,
                    'title' => $course->getTitle()->toArray(),
                    'slug' => $course->getSlug(),
                    'description' => $course->getDescription()->toArray(),
                    'lessonCount' => $course->getLessons()->count(),
                    'createdAt' => $course->getCreatedAt()->format(\DateTimeInterface::ATOM),
                    'coverUrl' => null !== $course->getCoverPath() ? '/api/courses/'.$id.'/cover' : null,
                    'enrolled' => isset($enrolledCourseIds[$id]),
                    'completedLessons' => $completedPerCourse[$id] ?? 0,
                ];
            },
            $courses->findBy([], ['createdAt' => 'DESC']),
        );

        return $this->json($data);
    }

    #[Route('/{slug}', name: 'show', methods: ['GET'])]
    public function show(
        #[MapEntity(mapping: ['slug' => 'slug'])] Course $course,
        #[CurrentUser] User $user,
    ): JsonResponse {
        $enrolled = null !== $this->enrollments->findOneByUserAndCourse($user, $course);

        $lessons = $course->getLessons()->toArray();
        $lessonIds = array_map(static fn (Lesson $lesson): int => (int) $lesson->getId(), $lessons);
        $completedIds = array_flip($this->completions->findCompletedLessonIds($user, $lessonIds));
        $passedQuizIds = array_flip($this->quizAttempts->passedQuizIdsForUser($user));

        $lessonQuizzes = [];
        foreach ($lessons as $lesson) {
            $lessonQuizzes[$lesson->getId()] = $this->quizInfo(
                $this->quizzes->findOneByLesson($lesson),
                $passedQuizIds,
            );
        }

        // Lazily award the certificate once every requirement is met.
        $certificate = $this->certificates->syncForCourse($user, $course);

        return $this->json([
            'id' => $course->getId(),
            'title' => $course->getTitle()->toArray(),
            'slug' => $course->getSlug(),
            'description' => $course->getDescription()->toArray(),
            'lessonCount' => \count($lessons),
            'createdAt' => $course->getCreatedAt()->format(\DateTimeInterface::ATOM),
            'coverUrl' => null !== $course->getCoverPath()
                ? '/api/courses/'.$course->getId().'/cover'
                : null,
            'enrolled' => $enrolled,
            'certificate' => null !== $certificate
                ? ['id' => $certificate->getId(), 'code' => $certificate->getCode()]
                : null,
            'quiz' => $this->quizInfo($this->quizzes->findOneByCourse($course), $passedQuizIds),
            'lessons' => array_map(static fn (Lesson $lesson): array => [
                'id' => $lesson->getId(),
                'title' => $lesson->getTitle()->toArray(),
                'position' => $lesson->getPosition(),
                'content' => $lesson->getContent()->toArray(),
                'youtubeUrl' => $lesson->getYoutubeUrl(),
                'videoUrl' => null !== $lesson->getVideoPath()
                    ? '/api/lessons/'.$lesson->getId().'/video'
                    : null,
                'pdfUrl' => null !== $lesson->getPdfPath()
                    ? '/api/lessons/'.$lesson->getId().'/pdf'
                    : null,
                'coverUrl' => null !== $lesson->getCoverPath()
                    ? '/api/lessons/'.$lesson->getId().'/cover'
                    : null,
                'completed' => isset($completedIds[$lesson->getId()]),
                'quiz' => $lessonQuizzes[$lesson->getId()],
            ], $lessons),
        ]);
    }

    #[Route('/{id<\d+>}/cover', name: 'cover', methods: ['GET'])]
    public function cover(Course $course): BinaryFileResponse
    {
        $name = $course->getCoverPath();
        if (null === $name || !is_file($this->media->path($name))) {
            throw $this->createNotFoundException('Ehhez a kurzushoz nincs borítókép.');
        }

        return new BinaryFileResponse($this->media->path($name));
    }

    /**
     * Compact quiz descriptor for the course detail view.
     *
     * @param array<int, mixed> $passedQuizIds
     *
     * @return array<string, mixed>|null
     */
    private function quizInfo(?Quiz $quiz, array $passedQuizIds): ?array
    {
        if (null === $quiz) {
            return null;
        }

        return [
            'id' => $quiz->getId(),
            'questionCount' => $quiz->getQuestions()->count(),
            'passed' => isset($passedQuizIds[$quiz->getId()]),
        ];
    }
}
