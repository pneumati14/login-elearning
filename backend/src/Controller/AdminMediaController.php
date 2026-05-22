<?php

namespace App\Controller;

use App\Entity\Lesson;
use App\Service\MediaStorage;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\File\UploadedFile;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Security\Http\Attribute\IsGranted;

/**
 * Lesson media uploads — administrators only. Videos and PDFs are
 * stored on disk; the lesson keeps a reference to the stored file.
 */
#[Route('/api/admin/lessons/{id<\d+>}', name: 'api_admin_lesson_media_')]
#[IsGranted('ROLE_ADMIN')]
final class AdminMediaController extends AbstractController
{
    public function __construct(
        private readonly EntityManagerInterface $entityManager,
        private readonly MediaStorage $storage,
    ) {
    }

    #[Route('/video', name: 'video_upload', methods: ['POST'])]
    public function uploadVideo(Lesson $lesson, Request $request): JsonResponse
    {
        $file = $request->files->get('file');
        if (!$file instanceof UploadedFile) {
            return $this->json(['error' => 'Nincs feltöltött fájl.'], JsonResponse::HTTP_BAD_REQUEST);
        }
        if (!str_starts_with((string) $file->getMimeType(), 'video/')) {
            return $this->json(['error' => 'A feltöltött fájl nem videó.'], JsonResponse::HTTP_UNPROCESSABLE_ENTITY);
        }

        $this->storage->delete($lesson->getVideoPath());
        $lesson->setVideoPath($this->storage->store($file));
        $this->entityManager->flush();

        return $this->json(['status' => 'uploaded']);
    }

    #[Route('/video', name: 'video_delete', methods: ['DELETE'])]
    public function deleteVideo(Lesson $lesson): JsonResponse
    {
        $this->storage->delete($lesson->getVideoPath());
        $lesson->setVideoPath(null);
        $this->entityManager->flush();

        return $this->json(['status' => 'deleted']);
    }

    #[Route('/pdf', name: 'pdf_upload', methods: ['POST'])]
    public function uploadPdf(Lesson $lesson, Request $request): JsonResponse
    {
        $file = $request->files->get('file');
        if (!$file instanceof UploadedFile) {
            return $this->json(['error' => 'Nincs feltöltött fájl.'], JsonResponse::HTTP_BAD_REQUEST);
        }
        if ('application/pdf' !== $file->getMimeType()) {
            return $this->json(['error' => 'A feltöltött fájl nem PDF.'], JsonResponse::HTTP_UNPROCESSABLE_ENTITY);
        }

        $this->storage->delete($lesson->getPdfPath());
        $lesson->setPdfPath($this->storage->store($file));
        $this->entityManager->flush();

        return $this->json(['status' => 'uploaded']);
    }

    #[Route('/pdf', name: 'pdf_delete', methods: ['DELETE'])]
    public function deletePdf(Lesson $lesson): JsonResponse
    {
        $this->storage->delete($lesson->getPdfPath());
        $lesson->setPdfPath(null);
        $this->entityManager->flush();

        return $this->json(['status' => 'deleted']);
    }

    #[Route('/cover', name: 'cover_upload', methods: ['POST'])]
    public function uploadCover(Lesson $lesson, Request $request): JsonResponse
    {
        $file = $request->files->get('file');
        if (!$file instanceof UploadedFile) {
            return $this->json(['error' => 'Nincs feltöltött fájl.'], JsonResponse::HTTP_BAD_REQUEST);
        }
        if (!str_starts_with((string) $file->getMimeType(), 'image/')) {
            return $this->json(['error' => 'A feltöltött fájl nem kép.'], JsonResponse::HTTP_UNPROCESSABLE_ENTITY);
        }

        $this->storage->delete($lesson->getCoverPath());
        $lesson->setCoverPath($this->storage->store($file));
        $this->entityManager->flush();

        return $this->json(['status' => 'uploaded']);
    }

    #[Route('/cover', name: 'cover_delete', methods: ['DELETE'])]
    public function deleteCover(Lesson $lesson): JsonResponse
    {
        $this->storage->delete($lesson->getCoverPath());
        $lesson->setCoverPath(null);
        $this->entityManager->flush();

        return $this->json(['status' => 'deleted']);
    }
}
