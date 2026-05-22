<?php

namespace App\Controller;

use App\Entity\Lesson;
use App\Service\MediaStorage;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\BinaryFileResponse;
use Symfony\Component\HttpFoundation\ResponseHeaderBag;
use Symfony\Component\Routing\Attribute\Route;

/**
 * Serves uploaded lesson media to authenticated users. Access is
 * restricted to ROLE_USER via the firewall (^/api/lessons).
 */
#[Route('/api/lessons/{id<\d+>}', name: 'api_lesson_media_')]
final class LessonMediaController extends AbstractController
{
    public function __construct(private readonly MediaStorage $storage)
    {
    }

    #[Route('/video', name: 'video', methods: ['GET'])]
    public function video(Lesson $lesson): BinaryFileResponse
    {
        $name = $lesson->getVideoPath();
        if (null === $name || !is_file($this->storage->path($name))) {
            throw $this->createNotFoundException('Ehhez a leckéhez nincs videó.');
        }

        // BinaryFileResponse honours Range requests, so videos can seek.
        return new BinaryFileResponse($this->storage->path($name));
    }

    #[Route('/pdf', name: 'pdf', methods: ['GET'])]
    public function pdf(Lesson $lesson): BinaryFileResponse
    {
        $name = $lesson->getPdfPath();
        if (null === $name || !is_file($this->storage->path($name))) {
            throw $this->createNotFoundException('Ehhez a leckéhez nincs PDF.');
        }

        $response = new BinaryFileResponse($this->storage->path($name));
        $response->headers->set('Content-Type', 'application/pdf');
        $response->setContentDisposition(ResponseHeaderBag::DISPOSITION_INLINE, 'lecke.pdf');

        return $response;
    }

    #[Route('/cover', name: 'cover', methods: ['GET'])]
    public function cover(Lesson $lesson): BinaryFileResponse
    {
        $name = $lesson->getCoverPath();
        if (null === $name || !is_file($this->storage->path($name))) {
            throw $this->createNotFoundException('Ehhez a leckéhez nincs borítókép.');
        }

        return new BinaryFileResponse($this->storage->path($name));
    }
}
