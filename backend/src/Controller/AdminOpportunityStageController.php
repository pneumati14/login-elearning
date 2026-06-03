<?php

namespace App\Controller;

use App\Entity\OpportunityStage;
use App\Entity\OpportunityType;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Security\Http\Attribute\IsGranted;

/**
 * Sub-resource of opportunity type: the ordered pipeline stages.
 * Administrators only. Every mutation returns the parent type with its
 * full (re-sorted) stage list so the client can refresh in one step.
 */
#[Route('/api/admin/opportunity-types/{typeId<\d+>}/stages', name: 'api_admin_opportunity_stages_')]
#[IsGranted('ROLE_ADMIN')]
final class AdminOpportunityStageController extends AbstractController
{
    public function __construct(
        private readonly EntityManagerInterface $entityManager,
    ) {
    }

    #[Route('', name: 'create', methods: ['POST'])]
    public function create(int $typeId, Request $request): JsonResponse
    {
        $type = $this->entityManager->find(OpportunityType::class, $typeId);
        if (!$type instanceof OpportunityType) {
            return $this->json(['error' => 'A típus nem található.'], JsonResponse::HTTP_NOT_FOUND);
        }

        $payload = $this->decode($request);
        if (null === $payload) {
            return $this->json(['error' => 'Érvénytelen kérés.'], JsonResponse::HTTP_BAD_REQUEST);
        }

        $name = trim((string) ($payload['name'] ?? ''));
        if ('' === $name) {
            return $this->json(['error' => 'A fázis neve kötelező.'], JsonResponse::HTTP_UNPROCESSABLE_ENTITY);
        }

        $stage = new OpportunityStage();
        $stage->setType($type)
            ->setName($name)
            ->setOutcome((string) ($payload['outcome'] ?? OpportunityStage::OUTCOME_OPEN))
            ->setPosition($this->nextStagePosition($type));

        $this->entityManager->persist($stage);
        $type->touch();
        $this->entityManager->flush();
        $this->entityManager->refresh($type);

        return $this->json($this->serializeType($type), JsonResponse::HTTP_CREATED);
    }

    #[Route('/{id<\d+>}', name: 'update', methods: ['PUT'])]
    public function update(int $typeId, int $id, Request $request): JsonResponse
    {
        $stage = $this->findStage($typeId, $id);
        if (null === $stage) {
            return $this->json(['error' => 'A fázis nem található.'], JsonResponse::HTTP_NOT_FOUND);
        }

        $payload = $this->decode($request);
        if (null === $payload) {
            return $this->json(['error' => 'Érvénytelen kérés.'], JsonResponse::HTTP_BAD_REQUEST);
        }

        $name = trim((string) ($payload['name'] ?? ''));
        if ('' === $name) {
            return $this->json(['error' => 'A fázis neve kötelező.'], JsonResponse::HTTP_UNPROCESSABLE_ENTITY);
        }

        $stage->setName($name);
        if (\array_key_exists('outcome', $payload)) {
            $stage->setOutcome((string) $payload['outcome']);
        }
        $stage->getType()->touch();
        $this->entityManager->flush();

        return $this->json($this->serializeType($stage->getType()));
    }

    #[Route('/{id<\d+>}', name: 'delete', methods: ['DELETE'])]
    public function delete(int $typeId, int $id): JsonResponse
    {
        $stage = $this->findStage($typeId, $id);
        if (null === $stage) {
            return $this->json(['error' => 'A fázis nem található.'], JsonResponse::HTTP_NOT_FOUND);
        }

        $type = $stage->getType();
        $this->entityManager->remove($stage);
        $type->touch();
        $this->entityManager->flush();
        $this->entityManager->refresh($type);

        return $this->json($this->serializeType($type));
    }

    /**
     * Reorder this type's stages. Body: { "order": [id, id, ...] }.
     */
    #[Route('/reorder', name: 'reorder', methods: ['PUT'])]
    public function reorder(int $typeId, Request $request): JsonResponse
    {
        $type = $this->entityManager->find(OpportunityType::class, $typeId);
        if (!$type instanceof OpportunityType) {
            return $this->json(['error' => 'A típus nem található.'], JsonResponse::HTTP_NOT_FOUND);
        }

        $payload = $this->decode($request);
        $order = $payload['order'] ?? null;
        if (!\is_array($order)) {
            return $this->json(['error' => 'Érvénytelen kérés.'], JsonResponse::HTTP_BAD_REQUEST);
        }

        $position = 0;
        foreach ($order as $stageId) {
            $stage = $this->findStage($typeId, (int) $stageId);
            if (null !== $stage) {
                $stage->setPosition($position++);
            }
        }
        $type->touch();
        $this->entityManager->flush();
        $this->entityManager->refresh($type);

        return $this->json($this->serializeType($type));
    }

    private function findStage(int $typeId, int $id): ?OpportunityStage
    {
        $stage = $this->entityManager->find(OpportunityStage::class, $id);
        if (!$stage instanceof OpportunityStage) {
            return null;
        }
        if ($stage->getType()->getId() !== $typeId) {
            return null;
        }

        return $stage;
    }

    private function nextStagePosition(OpportunityType $type): int
    {
        $max = -1;
        foreach ($type->getStages() as $s) {
            $max = max($max, $s->getPosition());
        }

        return $max + 1;
    }

    /**
     * @return array<string, mixed>
     */
    private function serializeType(OpportunityType $t): array
    {
        return [
            'id' => $t->getId(),
            'name' => $t->getName(),
            'position' => $t->getPosition(),
            'isActive' => $t->isActive(),
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
