<?php

namespace App\Controller;

use Doctrine\DBAL\Connection;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\Routing\Attribute\Route;

final class HealthController extends AbstractController
{
    #[Route('/api/health', name: 'api_health', methods: ['GET'])]
    public function health(Connection $connection): JsonResponse
    {
        try {
            $connection->executeQuery('SELECT 1');
            $database = 'up';
        } catch (\Throwable) {
            $database = 'down';
        }

        return $this->json([
            'status' => 'ok',
            'service' => 'login-elearning-api',
            'database' => $database,
            'timestamp' => (new \DateTimeImmutable())->format(\DateTimeInterface::ATOM),
        ]);
    }
}
