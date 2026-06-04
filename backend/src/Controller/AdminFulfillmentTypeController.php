<?php

namespace App\Controller;

use App\Entity\FulfillmentStage;
use App\Entity\FulfillmentType;
use App\Repository\FulfillmentTypeRepository;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Security\Http\Attribute\IsGranted;

/**
 * Fulfillment categories and their delivery stages. Reading is open to
 * sales staff (the board needs the stage lists); configuring is
 * administrators only. Every stage mutation returns the parent type
 * with its re-sorted stage list.
 */
#[Route('/api/admin/fulfillment-types', name: 'api_admin_fulfillment_types_')]
#[IsGranted('ROLE_SALES')]
final class AdminFulfillmentTypeController extends AbstractController
{
    public function __construct(
        private readonly EntityManagerInterface $entityManager,
        private readonly FulfillmentTypeRepository $types,
    ) {
    }

    #[Route('', name: 'list', methods: ['GET'])]
    public function list(): JsonResponse
    {
        return $this->json(array_map(
            fn (FulfillmentType $t): array => self::serializeType($t),
            $this->types->findAllOrdered(),
        ));
    }

    #[Route('', name: 'create', methods: ['POST'])]
    #[IsGranted('ROLE_ADMIN')]
    public function create(Request $request): JsonResponse
    {
        $payload = $this->decode($request);
        $name = trim((string) ($payload['name'] ?? ''));
        if ('' === $name) {
            return $this->json(['error' => 'A kategória neve kötelező.'], JsonResponse::HTTP_UNPROCESSABLE_ENTITY);
        }

        $max = -1;
        foreach ($this->types->findAll() as $t) {
            $max = max($max, $t->getPosition());
        }

        $type = new FulfillmentType();
        $type->setName($name)->setPosition($max + 1);
        $this->entityManager->persist($type);
        $this->entityManager->flush();

        return $this->json(self::serializeType($type), JsonResponse::HTTP_CREATED);
    }

    #[Route('/{id<\d+>}', name: 'update', methods: ['PUT'])]
    #[IsGranted('ROLE_ADMIN')]
    public function update(FulfillmentType $type, Request $request): JsonResponse
    {
        $payload = $this->decode($request);
        $name = trim((string) ($payload['name'] ?? ''));
        if ('' === $name) {
            return $this->json(['error' => 'A kategória neve kötelező.'], JsonResponse::HTTP_UNPROCESSABLE_ENTITY);
        }

        $type->setName($name);
        $type->touch();
        $this->entityManager->flush();

        return $this->json(self::serializeType($type));
    }

    #[Route('/{id<\d+>}', name: 'delete', methods: ['DELETE'])]
    #[IsGranted('ROLE_ADMIN')]
    public function delete(FulfillmentType $type): JsonResponse
    {
        $this->entityManager->remove($type);
        $this->entityManager->flush();

        return $this->json(['status' => 'deleted']);
    }

    // ── Stages ───────────────────────────────────────────────────────

    #[Route('/{typeId<\d+>}/stages', name: 'stage_create', methods: ['POST'])]
    #[IsGranted('ROLE_ADMIN')]
    public function createStage(int $typeId, Request $request): JsonResponse
    {
        $type = $this->entityManager->find(FulfillmentType::class, $typeId);
        if (!$type instanceof FulfillmentType) {
            return $this->json(['error' => 'A kategória nem található.'], JsonResponse::HTTP_NOT_FOUND);
        }

        $payload = $this->decode($request);
        $name = trim((string) ($payload['name'] ?? ''));
        if ('' === $name) {
            return $this->json(['error' => 'A stage neve kötelező.'], JsonResponse::HTTP_UNPROCESSABLE_ENTITY);
        }

        $max = -1;
        foreach ($type->getStages() as $s) {
            $max = max($max, $s->getPosition());
        }

        $stage = new FulfillmentStage();
        $stage->setType($type)
            ->setName($name)
            ->setIsDone((bool) ($payload['isDone'] ?? false))
            ->setPosition($max + 1);

        $this->entityManager->persist($stage);
        $type->touch();
        $this->entityManager->flush();
        $this->entityManager->refresh($type);

        return $this->json(self::serializeType($type), JsonResponse::HTTP_CREATED);
    }

    #[Route('/{typeId<\d+>}/stages/{id<\d+>}', name: 'stage_update', methods: ['PUT'])]
    #[IsGranted('ROLE_ADMIN')]
    public function updateStage(int $typeId, int $id, Request $request): JsonResponse
    {
        $stage = $this->findStage($typeId, $id);
        if (null === $stage) {
            return $this->json(['error' => 'A stage nem található.'], JsonResponse::HTTP_NOT_FOUND);
        }

        $payload = $this->decode($request);
        $name = trim((string) ($payload['name'] ?? ''));
        if ('' === $name) {
            return $this->json(['error' => 'A stage neve kötelező.'], JsonResponse::HTTP_UNPROCESSABLE_ENTITY);
        }

        $stage->setName($name);
        if (\array_key_exists('isDone', $payload)) {
            $stage->setIsDone((bool) $payload['isDone']);
        }
        $stage->getType()->touch();
        $this->entityManager->flush();

        return $this->json(self::serializeType($stage->getType()));
    }

    #[Route('/{typeId<\d+>}/stages/{id<\d+>}', name: 'stage_delete', methods: ['DELETE'])]
    #[IsGranted('ROLE_ADMIN')]
    public function deleteStage(int $typeId, int $id): JsonResponse
    {
        $stage = $this->findStage($typeId, $id);
        if (null === $stage) {
            return $this->json(['error' => 'A stage nem található.'], JsonResponse::HTTP_NOT_FOUND);
        }

        $type = $stage->getType();
        $this->entityManager->remove($stage);
        $type->touch();
        $this->entityManager->flush();
        $this->entityManager->refresh($type);

        return $this->json(self::serializeType($type));
    }

    /**
     * Reorder a type's stages. Body: { "order": [id, id, ...] }.
     */
    #[Route('/{typeId<\d+>}/stages/reorder', name: 'stage_reorder', methods: ['PUT'])]
    #[IsGranted('ROLE_ADMIN')]
    public function reorderStages(int $typeId, Request $request): JsonResponse
    {
        $type = $this->entityManager->find(FulfillmentType::class, $typeId);
        if (!$type instanceof FulfillmentType) {
            return $this->json(['error' => 'A kategória nem található.'], JsonResponse::HTTP_NOT_FOUND);
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

        return $this->json(self::serializeType($type));
    }

    private function findStage(int $typeId, int $id): ?FulfillmentStage
    {
        $stage = $this->entityManager->find(FulfillmentStage::class, $id);
        if (!$stage instanceof FulfillmentStage || $stage->getType()->getId() !== $typeId) {
            return null;
        }

        return $stage;
    }

    /**
     * Shared with the board controller so both emit the same shape.
     *
     * @return array<string, mixed>
     */
    public static function serializeType(FulfillmentType $t): array
    {
        return [
            'id' => $t->getId(),
            'name' => $t->getName(),
            'position' => $t->getPosition(),
            'stages' => array_map(
                fn (FulfillmentStage $s): array => [
                    'id' => $s->getId(),
                    'name' => $s->getName(),
                    'position' => $s->getPosition(),
                    'isDone' => $s->isDone(),
                ],
                $t->getStages()->toArray(),
            ),
        ];
    }

    /**
     * @return array<string, mixed>
     */
    private function decode(Request $request): ?array
    {
        $payload = json_decode($request->getContent(), true);

        return \is_array($payload) ? $payload : [];
    }
}
