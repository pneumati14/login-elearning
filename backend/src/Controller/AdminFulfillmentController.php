<?php

namespace App\Controller;

use App\Entity\Customer;
use App\Entity\FulfillmentStage;
use App\Entity\FulfillmentType;
use App\Entity\Opportunity;
use App\Entity\OpportunityStage;
use App\Repository\OpportunityRepository;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Security\Http\Attribute\IsGranted;

/**
 * The fulfillment board: every won deal lands here automatically. An
 * uncategorised deal is assigned a fulfillment category (it enters the
 * category's first stage), then moves through the delivery stages via
 * drag-and-drop.
 */
#[Route('/api/admin/fulfillment', name: 'api_admin_fulfillment_')]
#[IsGranted('ROLE_SALES')]
final class AdminFulfillmentController extends AbstractController
{
    public function __construct(
        private readonly EntityManagerInterface $entityManager,
        private readonly OpportunityRepository $opportunities,
    ) {
    }

    #[Route('', name: 'list', methods: ['GET'])]
    public function list(): JsonResponse
    {
        /** @var Opportunity[] $won */
        $won = $this->opportunities->createQueryBuilder('o')
            ->addSelect('s', 'c')
            ->join('o.stage', 's')
            ->join('o.customer', 'c')
            ->andWhere('s.outcome = :won')
            ->andWhere('c.deletedAt IS NULL')
            ->setParameter('won', OpportunityStage::OUTCOME_WON)
            ->orderBy('o.closedAt', 'DESC')
            ->addOrderBy('o.id', 'DESC')
            ->getQuery()
            ->getResult();

        return $this->json(array_map(fn (Opportunity $o): array => $this->serialize($o), $won));
    }

    /**
     * Categorise a won deal: it enters the category's first stage.
     * Body: { "typeId": <id> }. A null/empty typeId clears the category.
     */
    #[Route('/{id<\d+>}/assign', name: 'assign', methods: ['PUT'])]
    public function assign(int $id, Request $request): JsonResponse
    {
        $opportunity = $this->findWon($id);
        if (null === $opportunity) {
            return $this->json(['error' => 'A megnyert ügylet nem található.'], JsonResponse::HTTP_NOT_FOUND);
        }

        $payload = json_decode($request->getContent(), true);
        $typeId = \is_array($payload) ? ($payload['typeId'] ?? null) : null;

        if (null === $typeId || '' === $typeId) {
            $opportunity->setFulfillmentType(null)->setFulfillmentStage(null);
        } else {
            $type = $this->entityManager->find(FulfillmentType::class, (int) $typeId);
            if (!$type instanceof FulfillmentType) {
                return $this->json(['error' => 'A kategória nem található.'], JsonResponse::HTTP_NOT_FOUND);
            }
            $first = $type->getStages()->first();
            if (!$first instanceof FulfillmentStage) {
                return $this->json(['error' => 'A kategóriának még nincs stage-e.'], JsonResponse::HTTP_UNPROCESSABLE_ENTITY);
            }
            $opportunity->setFulfillmentType($type)->setFulfillmentStage($first);
        }

        $opportunity->touch();
        $this->entityManager->flush();

        return $this->json($this->serialize($opportunity));
    }

    /**
     * Move a categorised deal to another stage of its category (the
     * kanban drag-and-drop). Body: { "stageId": <id> }.
     */
    #[Route('/{id<\d+>}/stage', name: 'move_stage', methods: ['PUT'])]
    public function moveStage(int $id, Request $request): JsonResponse
    {
        $opportunity = $this->findWon($id);
        if (null === $opportunity) {
            return $this->json(['error' => 'A megnyert ügylet nem található.'], JsonResponse::HTTP_NOT_FOUND);
        }

        $type = $opportunity->getFulfillmentType();
        if (null === $type) {
            return $this->json(['error' => 'Előbb sorold be az ügyletet egy kategóriába.'], JsonResponse::HTTP_UNPROCESSABLE_ENTITY);
        }

        $payload = json_decode($request->getContent(), true);
        $stageId = \is_array($payload) ? (int) ($payload['stageId'] ?? 0) : 0;
        $stage = $this->entityManager->find(FulfillmentStage::class, $stageId);
        if (!$stage instanceof FulfillmentStage || $stage->getType()->getId() !== $type->getId()) {
            return $this->json(['error' => 'A stage nem található ebben a kategóriában.'], JsonResponse::HTTP_UNPROCESSABLE_ENTITY);
        }

        $opportunity->setFulfillmentStage($stage);
        $opportunity->touch();
        $this->entityManager->flush();

        return $this->json($this->serialize($opportunity));
    }

    private function findWon(int $id): ?Opportunity
    {
        $opportunity = $this->entityManager->find(Opportunity::class, $id);
        if (!$opportunity instanceof Opportunity) {
            return null;
        }
        if (OpportunityStage::OUTCOME_WON !== $opportunity->getStage()->getOutcome()) {
            return null;
        }
        if (null !== $opportunity->getCustomer()->getDeletedAt()) {
            return null;
        }

        return $opportunity;
    }

    /**
     * @return array<string, mixed>
     */
    private function serialize(Opportunity $o): array
    {
        $customer = $o->getCustomer();

        return [
            'id' => $o->getId(),
            'title' => $o->getTitle(),
            'customerId' => $customer->getId(),
            'customerName' => $customer->getName(),
            'value' => $o->getLineItems()->isEmpty() ? $o->getValue() : $o->getLineItemsTotal(),
            'currency' => $o->getCurrency(),
            'closedAt' => $o->getClosedAt()?->format('Y-m-d'),
            'ownerName' => $this->currentOwnerName($customer),
            'fulfillmentTypeId' => $o->getFulfillmentType()?->getId(),
            'fulfillmentStageId' => $o->getFulfillmentStage()?->getId(),
        ];
    }

    /** The salesperson currently responsible, from the active assignment. */
    private function currentOwnerName(Customer $customer): ?string
    {
        $today = new \DateTimeImmutable('today');
        foreach ($customer->getSalesAssignments() as $assignment) {
            if ($assignment->isActiveOn($today)) {
                $user = $assignment->getUser();

                return trim($user->getFirstName().' '.$user->getLastName()) ?: $user->getEmail();
            }
        }

        return null;
    }
}
