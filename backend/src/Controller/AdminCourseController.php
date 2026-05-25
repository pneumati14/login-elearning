<?php

namespace App\Controller;

use App\Entity\Course;
use App\Entity\Lesson;
use App\Entity\LocalizedText;
use App\Repository\CourseRepository;
use App\Service\MediaStorage;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\File\UploadedFile;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Security\Http\Attribute\IsGranted;

/**
 * Course and lesson authoring — administrators only.
 */
#[Route('/api/admin', name: 'api_admin_')]
#[IsGranted('ROLE_ADMIN')]
final class AdminCourseController extends AbstractController
{
    public function __construct(
        private readonly EntityManagerInterface $entityManager,
        private readonly CourseRepository $courses,
        private readonly MediaStorage $media,
    ) {
    }

    #[Route('/courses', name: 'course_create', methods: ['POST'])]
    public function createCourse(Request $request): JsonResponse
    {
        $payload = $this->decode($request);
        if (null === $payload) {
            return $this->json(['error' => 'Érvénytelen kérés.'], JsonResponse::HTTP_BAD_REQUEST);
        }

        $course = new Course();
        $this->applyLocalized($course->getTitle(), $payload['title'] ?? null);
        $this->applyLocalized($course->getDescription(), $payload['description'] ?? null);
        if ('' === $course->getTitle()->getEn()) {
            return $this->json(['error' => 'A kurzus angol címe kötelező.'], JsonResponse::HTTP_UNPROCESSABLE_ENTITY);
        }
        $course->setSlug($this->uniqueSlug($this->slugify($course->getTitle()->getEn())));

        $this->entityManager->persist($course);
        $this->entityManager->flush();

        return $this->json($this->serializeCourse($course), JsonResponse::HTTP_CREATED);
    }

    #[Route('/courses/{id<\d+>}', name: 'course_update', methods: ['PUT'])]
    public function updateCourse(Course $course, Request $request): JsonResponse
    {
        $payload = $this->decode($request);
        if (null === $payload) {
            return $this->json(['error' => 'Érvénytelen kérés.'], JsonResponse::HTTP_BAD_REQUEST);
        }

        $this->applyLocalized($course->getTitle(), $payload['title'] ?? null);
        $this->applyLocalized($course->getDescription(), $payload['description'] ?? null);
        if ('' === $course->getTitle()->getEn()) {
            return $this->json(['error' => 'A kurzus angol címe kötelező.'], JsonResponse::HTTP_UNPROCESSABLE_ENTITY);
        }
        $this->entityManager->flush();

        return $this->json($this->serializeCourse($course));
    }

    #[Route('/courses/{id<\d+>}', name: 'course_delete', methods: ['DELETE'])]
    public function deleteCourse(Course $course): JsonResponse
    {
        $this->media->delete($course->getCoverPath());
        foreach ($course->getLessons() as $lesson) {
            $this->media->delete($lesson->getVideoPath());
            $this->media->delete($lesson->getPdfPath());
            $this->media->delete($lesson->getCoverPath());
        }

        $this->entityManager->remove($course);
        $this->entityManager->flush();

        return $this->json(['status' => 'deleted']);
    }

    #[Route('/courses/{id<\d+>}/cover', name: 'course_cover_upload', methods: ['POST'])]
    public function uploadCourseCover(Course $course, Request $request): JsonResponse
    {
        $file = $request->files->get('file');
        if (!$file instanceof UploadedFile) {
            return $this->json(['error' => 'Nincs feltöltött fájl.'], JsonResponse::HTTP_BAD_REQUEST);
        }
        if (!str_starts_with((string) $file->getMimeType(), 'image/')) {
            return $this->json(['error' => 'A feltöltött fájl nem kép.'], JsonResponse::HTTP_UNPROCESSABLE_ENTITY);
        }

        $this->media->delete($course->getCoverPath());
        $course->setCoverPath($this->media->store($file));
        $this->entityManager->flush();

        return $this->json(['status' => 'uploaded']);
    }

    #[Route('/courses/{id<\d+>}/cover', name: 'course_cover_delete', methods: ['DELETE'])]
    public function deleteCourseCover(Course $course): JsonResponse
    {
        $this->media->delete($course->getCoverPath());
        $course->setCoverPath(null);
        $this->entityManager->flush();

        return $this->json(['status' => 'deleted']);
    }

    #[Route('/courses/{id<\d+>}/lessons', name: 'lesson_create', methods: ['POST'])]
    public function createLesson(Course $course, Request $request): JsonResponse
    {
        $payload = $this->decode($request);
        if (null === $payload) {
            return $this->json(['error' => 'Érvénytelen kérés.'], JsonResponse::HTTP_BAD_REQUEST);
        }

        $maxPosition = 0;
        foreach ($course->getLessons() as $existing) {
            $maxPosition = max($maxPosition, $existing->getPosition());
        }

        $lesson = new Lesson();
        $this->applyLocalized($lesson->getTitle(), $payload['title'] ?? null);
        $this->applyLocalized($lesson->getContent(), $payload['content'] ?? null);
        if ('' === $lesson->getTitle()->getEn()) {
            return $this->json(['error' => 'A lecke angol címe kötelező.'], JsonResponse::HTTP_UNPROCESSABLE_ENTITY);
        }
        $lesson->setPosition($maxPosition + 1);
        $course->addLesson($lesson);

        $this->entityManager->persist($lesson);
        $this->entityManager->flush();

        return $this->json($this->serializeLesson($lesson), JsonResponse::HTTP_CREATED);
    }

    #[Route('/lessons/{id<\d+>}', name: 'lesson_update', methods: ['PUT'])]
    public function updateLesson(Lesson $lesson, Request $request): JsonResponse
    {
        $payload = $this->decode($request);
        if (null === $payload) {
            return $this->json(['error' => 'Érvénytelen kérés.'], JsonResponse::HTTP_BAD_REQUEST);
        }

        $this->applyLocalized($lesson->getTitle(), $payload['title'] ?? null);
        $this->applyLocalized($lesson->getContent(), $payload['content'] ?? null);
        if ('' === $lesson->getTitle()->getEn()) {
            return $this->json(['error' => 'A lecke angol címe kötelező.'], JsonResponse::HTTP_UNPROCESSABLE_ENTITY);
        }
        if (isset($payload['position']) && is_numeric($payload['position'])) {
            $lesson->setPosition((int) $payload['position']);
        }
        if (\array_key_exists('youtubeUrl', $payload)) {
            $youtube = trim((string) ($payload['youtubeUrl'] ?? ''));
            $lesson->setYoutubeUrl('' !== $youtube ? $youtube : null);
        }
        $this->entityManager->flush();

        return $this->json($this->serializeLesson($lesson));
    }

    #[Route('/lessons/{id<\d+>}', name: 'lesson_delete', methods: ['DELETE'])]
    public function deleteLesson(Lesson $lesson): JsonResponse
    {
        $this->media->delete($lesson->getVideoPath());
        $this->media->delete($lesson->getPdfPath());
        $this->media->delete($lesson->getCoverPath());

        $this->entityManager->remove($lesson);
        $this->entityManager->flush();

        return $this->json(['status' => 'deleted']);
    }

    /**
     * @return array<string, mixed>|null
     */
    private function decode(Request $request): ?array
    {
        $payload = json_decode($request->getContent(), true);

        return \is_array($payload) ? $payload : null;
    }

    /**
     * Applies a multilingual { en, hu, az, de, pt, tr, pl, es } payload value onto a LocalizedText field.
     */
    private function applyLocalized(LocalizedText $field, mixed $value): void
    {
        $en = \is_array($value) ? trim((string) ($value['en'] ?? '')) : '';
        $hu = \is_array($value) ? trim((string) ($value['hu'] ?? '')) : '';
        $az = \is_array($value) ? trim((string) ($value['az'] ?? '')) : '';
        $de = \is_array($value) ? trim((string) ($value['de'] ?? '')) : '';
        $pt = \is_array($value) ? trim((string) ($value['pt'] ?? '')) : '';
        $tr = \is_array($value) ? trim((string) ($value['tr'] ?? '')) : '';
        $pl = \is_array($value) ? trim((string) ($value['pl'] ?? '')) : '';
        $es = \is_array($value) ? trim((string) ($value['es'] ?? '')) : '';
        $field->setEn($en)->setHu($hu)->setAz($az)->setDe($de)->setPt($pt)->setTr($tr)->setPl($pl)->setEs($es);
    }

    /**
     * @return array<string, mixed>
     */
    private function serializeCourse(Course $course): array
    {
        return [
            'id' => $course->getId(),
            'title' => $course->getTitle()->toArray(),
            'slug' => $course->getSlug(),
            'description' => $course->getDescription()->toArray(),
            'lessonCount' => $course->getLessons()->count(),
            'createdAt' => $course->getCreatedAt()->format(\DateTimeInterface::ATOM),
            'coverUrl' => null !== $course->getCoverPath()
                ? '/api/courses/'.$course->getId().'/cover'
                : null,
        ];
    }

    /**
     * @return array<string, mixed>
     */
    private function serializeLesson(Lesson $lesson): array
    {
        return [
            'id' => $lesson->getId(),
            'title' => $lesson->getTitle()->toArray(),
            'content' => $lesson->getContent()->toArray(),
            'position' => $lesson->getPosition(),
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
        ];
    }

    private function slugify(string $text): string
    {
        $text = strtr($text, [
            'á' => 'a', 'é' => 'e', 'í' => 'i', 'ó' => 'o', 'ö' => 'o',
            'ő' => 'o', 'ú' => 'u', 'ü' => 'u', 'ű' => 'u',
            'Á' => 'a', 'É' => 'e', 'Í' => 'i', 'Ó' => 'o', 'Ö' => 'o',
            'Ő' => 'o', 'Ú' => 'u', 'Ü' => 'u', 'Ű' => 'u',
        ]);
        $text = strtolower($text);
        $text = preg_replace('/[^a-z0-9]+/', '-', $text) ?? '';
        $text = trim($text, '-');

        return '' !== $text ? $text : 'kurzus';
    }

    private function uniqueSlug(string $base): string
    {
        $slug = $base;
        $suffix = 2;
        while (null !== $this->courses->findOneBy(['slug' => $slug])) {
            $slug = $base.'-'.$suffix;
            ++$suffix;
        }

        return $slug;
    }
}
