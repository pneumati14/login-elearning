<?php

namespace App\Controller;

use App\Entity\OpportunityStage;
use App\Entity\OpportunityType;
use App\Repository\OpportunityTypeRepository;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Security\Http\Attribute\IsGranted;

/**
 * Opportunity type (sales pipeline) configuration. Reading the list is
 * open to sales staff (they pick a type and move deals through its
 * stages); configuring the types and their stages is administrators
 * only. Each type owns an ordered list of stages, managed via the
 * nested stages controller. Hard delete.
 */
#[Route('/api/admin/opportunity-types', name: 'api_admin_opportunity_types_')]
#[IsGranted('ROLE_SALES')]
final class AdminOpportunityTypeController extends AbstractController
{
    public function __construct(
        private readonly EntityManagerInterface $entityManager,
        private readonly OpportunityTypeRepository $types,
    ) {
    }

    #[Route('', name: 'list', methods: ['GET'])]
    public function list(): JsonResponse
    {
        $data = array_map(
            fn (OpportunityType $t): array => $this->serialize($t),
            $this->types->findAllOrdered(),
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

        $name = trim((string) ($payload['name'] ?? ''));
        if ('' === $name) {
            return $this->json(['error' => 'A típus neve kötelező.'], JsonResponse::HTTP_UNPROCESSABLE_ENTITY);
        }

        $type = new OpportunityType();
        $type->setName($name)->setPosition($this->nextTypePosition());
        if (\array_key_exists('isActive', $payload)) {
            $type->setIsActive((bool) $payload['isActive']);
        }
        $this->applyValidity($type, $payload);

        $this->entityManager->persist($type);
        $this->entityManager->flush();

        return $this->json($this->serialize($type), JsonResponse::HTTP_CREATED);
    }

    #[Route('/{id<\d+>}', name: 'update', methods: ['PUT'])]
    #[IsGranted('ROLE_ADMIN')]
    public function update(OpportunityType $type, Request $request): JsonResponse
    {
        $payload = $this->decode($request);
        if (null === $payload) {
            return $this->json(['error' => 'Érvénytelen kérés.'], JsonResponse::HTTP_BAD_REQUEST);
        }

        $name = trim((string) ($payload['name'] ?? ''));
        if ('' === $name) {
            return $this->json(['error' => 'A típus neve kötelező.'], JsonResponse::HTTP_UNPROCESSABLE_ENTITY);
        }

        $type->setName($name);
        if (\array_key_exists('isActive', $payload)) {
            $type->setIsActive((bool) $payload['isActive']);
        }
        $this->applyValidity($type, $payload);
        $type->touch();
        $this->entityManager->flush();

        return $this->json($this->serialize($type));
    }

    #[Route('/{id<\d+>}', name: 'delete', methods: ['DELETE'])]
    #[IsGranted('ROLE_ADMIN')]
    public function delete(OpportunityType $type): JsonResponse
    {
        $this->entityManager->remove($type);
        $this->entityManager->flush();

        return $this->json(['status' => 'deleted']);
    }

    /**
     * Reorder types. Body: { "order": [id, id, ...] }. Any id missing
     * from the list keeps its relative order after the listed ones.
     */
    #[Route('/reorder', name: 'reorder', methods: ['PUT'])]
    #[IsGranted('ROLE_ADMIN')]
    public function reorder(Request $request): JsonResponse
    {
        $payload = $this->decode($request);
        $order = $payload['order'] ?? null;
        if (!\is_array($order)) {
            return $this->json(['error' => 'Érvénytelen kérés.'], JsonResponse::HTTP_BAD_REQUEST);
        }

        $position = 0;
        foreach ($order as $id) {
            $type = $this->entityManager->find(OpportunityType::class, (int) $id);
            if ($type instanceof OpportunityType) {
                $type->setPosition($position++);
            }
        }
        $this->entityManager->flush();

        $data = array_map(
            fn (OpportunityType $t): array => $this->serialize($t),
            $this->types->findAllOrdered(),
        );

        return $this->json($data);
    }

    /**
     * Set the validity window from the payload. Only keys that are present
     * are touched, so a partial update leaves the others alone; an explicit
     * null or empty string clears that bound.
     *
     * @param array<string, mixed> $payload
     */
    private function applyValidity(OpportunityType $type, array $payload): void
    {
        if (\array_key_exists('validFrom', $payload)) {
            $type->setValidFrom($this->parseDate($payload['validFrom']));
        }
        if (\array_key_exists('validUntil', $payload)) {
            $type->setValidUntil($this->parseDate($payload['validUntil']));
        }
    }

    private function parseDate(mixed $value): ?\DateTimeImmutable
    {
        if (!\is_string($value) || '' === trim($value)) {
            return null;
        }

        $date = \DateTimeImmutable::createFromFormat('Y-m-d', trim($value));

        return false === $date ? null : $date->setTime(0, 0);
    }

    private function nextTypePosition(): int
    {
        $max = 0;
        foreach ($this->types->findAll() as $t) {
            $max = max($max, $t->getPosition());
        }

        return $max + 1;
    }

    /**
     * @return array<string, mixed>
     */
    private function serialize(OpportunityType $t): array
    {
        return [
            'id' => $t->getId(),
            'name' => $t->getName(),
            'position' => $t->getPosition(),
            'isActive' => $t->isActive(),
            'validFrom' => $t->getValidFrom()?->format('Y-m-d'),
            'validUntil' => $t->getValidUntil()?->format('Y-m-d'),
            'stages' => array_map(
                fn (OpportunityStage $s): array => [
                    'id' => $s->getId(),
                    'name' => $s->getName(),
                    'position' => $s->getPosition(),
                    'outcome' => $s->getOutcome(),
                ],
                $t->getStages()->toArray(),
            ),
            'createdAt' => $t->getCreatedAt()->format(\DateTimeInterface::ATOM),
            'updatedAt' => $t->getUpdatedAt()->format(\DateTimeInterface::ATOM),
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
