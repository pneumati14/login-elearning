<?php

namespace App\Controller;

use App\Entity\Integration;
use App\Repository\IntegrationRepository;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Security\Http\Attribute\IsGranted;

/**
 * Integration master list (payroll / ERP / access control / other
 * systems). Reading is open to sales staff (they pick integrations on
 * the customer's architecture tab); managing the list is administrators
 * only. Hard delete — the customer links disappear with the row.
 */
#[Route('/api/admin/integrations', name: 'api_admin_integrations_')]
#[IsGranted('ROLE_SALES')]
final class AdminIntegrationController extends AbstractController
{
    public function __construct(
        private readonly EntityManagerInterface $entityManager,
        private readonly IntegrationRepository $integrations,
    ) {
    }

    #[Route('', name: 'list', methods: ['GET'])]
    public function list(): JsonResponse
    {
        $data = array_map(
            fn (Integration $i): array => $this->serialize($i),
            $this->integrations->findAllOrdered(),
        );

        return $this->json($data);
    }

    #[Route('', name: 'create', methods: ['POST'])]
    #[IsGranted('ROLE_ADMIN')]
    public function create(Request $request): JsonResponse
    {
        $payload = $this->decode($request);
        if (null === $payload) {
            return $this->json(['error' => 'Érvénytelen kérés.'], JsonResponse::HTTP_BAD_REQUEST);
        }

        $integration = new Integration();
        $error = $this->apply($integration, $payload);
        if (null !== $error) {
            return $this->json(['error' => $error], JsonResponse::HTTP_UNPROCESSABLE_ENTITY);
        }

        $this->entityManager->persist($integration);
        $this->entityManager->flush();

        return $this->json($this->serialize($integration), JsonResponse::HTTP_CREATED);
    }

    #[Route('/{id<\d+>}', name: 'update', methods: ['PUT'])]
    #[IsGranted('ROLE_ADMIN')]
    public function update(Integration $integration, Request $request): JsonResponse
    {
        $payload = $this->decode($request);
        if (null === $payload) {
            return $this->json(['error' => 'Érvénytelen kérés.'], JsonResponse::HTTP_BAD_REQUEST);
        }

        $error = $this->apply($integration, $payload);
        if (null !== $error) {
            return $this->json(['error' => $error], JsonResponse::HTTP_UNPROCESSABLE_ENTITY);
        }

        $integration->touch();
        $this->entityManager->flush();

        return $this->json($this->serialize($integration));
    }

    #[Route('/{id<\d+>}', name: 'delete', methods: ['DELETE'])]
    #[IsGranted('ROLE_ADMIN')]
    public function delete(Integration $integration): JsonResponse
    {
        $this->entityManager->remove($integration);
        $this->entityManager->flush();

        return $this->json(['status' => 'deleted']);
    }

    /**
     * @param array<string, mixed> $payload
     */
    private function apply(Integration $integration, array $payload): ?string
    {
        $name = trim((string) ($payload['name'] ?? ''));
        if ('' === $name) {
            return 'Az integráció megnevezése kötelező.';
        }

        $category = (string) ($payload['category'] ?? '');
        if (!\in_array($category, Integration::CATEGORIES, true)) {
            return 'Érvénytelen kategória.';
        }

        $integration->setName($name)
            ->setCategory($category)
            ->setIsActive((bool) ($payload['isActive'] ?? true));

        return null;
    }

    /**
     * @return array<string, mixed>
     */
    private function serialize(Integration $i): array
    {
        return [
            'id' => $i->getId(),
            'name' => $i->getName(),
            'category' => $i->getCategory(),
            'isActive' => $i->isActive(),
            'createdAt' => $i->getCreatedAt()->format(\DateTimeInterface::ATOM),
            'updatedAt' => $i->getUpdatedAt()->format(\DateTimeInterface::ATOM),
        ];
    }

    /**
     * @return array<string, mixed>|null
     */
    private function decode(Request $request): ?array
    {
        $payload = json_decode($request->getContent(), true);

        return \is_array($payload) ? $payload : null;
    }
}
