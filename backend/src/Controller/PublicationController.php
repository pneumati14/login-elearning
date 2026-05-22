<?php

namespace App\Controller;

use App\Entity\Publication;
use App\Repository\PublicationRepository;
use App\Service\PublicationStorage;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\BinaryFileResponse;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\ResponseHeaderBag;
use Symfony\Component\Routing\Attribute\Route;

/**
 * Public access to research publications — anyone can list and open them.
 */
#[Route('/api/publications', name: 'api_publications_')]
final class PublicationController extends AbstractController
{
    #[Route('', name: 'list', methods: ['GET'])]
    public function list(PublicationRepository $publications): JsonResponse
    {
        return $this->json(array_map(static fn (Publication $publication): array => [
            'id' => $publication->getId(),
            'title' => $publication->getTitle()->toArray(),
            'description' => $publication->getDescription()->toArray(),
            'topic' => $publication->getTopic()->toArray(),
            'author' => $publication->getAuthor()->toArray(),
            'fileUrl' => '/api/publications/'.$publication->getId().'/file',
            'createdAt' => $publication->getCreatedAt()->format(\DateTimeInterface::ATOM),
        ], $publications->findNewestFirst()));
    }

    #[Route('/{id<\d+>}/file', name: 'file', methods: ['GET'])]
    public function download(Publication $publication, PublicationStorage $storage): BinaryFileResponse
    {
        $path = $storage->path($publication->getFilePath());
        if (!is_file($path)) {
            throw $this->createNotFoundException('A publikáció fájlja nem található.');
        }

        $name = $publication->getOriginalName();
        $fallback = preg_replace('/[^\x20-\x7e]/', '_', $name) ?: 'publication.pdf';

        $response = new BinaryFileResponse($path);
        $response->headers->set('Content-Type', 'application/pdf');
        $response->setContentDisposition(ResponseHeaderBag::DISPOSITION_INLINE, $name, $fallback);

        return $response;
    }
}
