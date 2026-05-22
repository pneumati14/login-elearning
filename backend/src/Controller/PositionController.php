<?php

namespace App\Controller;

use App\Entity\Position;
use App\Repository\PositionRepository;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\Routing\Attribute\Route;

/**
 * Public access to open job positions — shown on the Team & Career page.
 * Each text field is returned in both languages ({en, hu}); the client
 * picks the active locale and falls back to English.
 */
#[Route('/api/positions', name: 'api_positions_')]
final class PositionController extends AbstractController
{
    #[Route('', name: 'list', methods: ['GET'])]
    public function list(PositionRepository $positions): JsonResponse
    {
        return $this->json(array_map(static fn (Position $position): array => [
            'id' => $position->getId(),
            'title' => $position->getTitle()->toArray(),
            'location' => $position->getLocation()->toArray(),
            'type' => $position->getType()->toArray(),
            'summary' => $position->getSummary()->toArray(),
        ], $positions->findAllOrdered()));
    }
}
