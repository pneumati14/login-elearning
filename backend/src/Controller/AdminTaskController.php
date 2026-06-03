<?php

namespace App\Controller;

use App\Entity\Activity;
use App\Entity\User;
use App\Repository\ActivityRepository;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Security\Http\Attribute\IsGranted;

/**
 * Cross-customer task feed for the task dashboard — administrators only.
 * Tasks are [[Activity]] rows of type "task". `scope=mine` (default)
 * limits to the current admin's tasks; `scope=all` returns everyone's.
 * Mutations (mark done, edit) still go through the per-customer activity
 * controller.
 */
#[Route('/api/admin/tasks', name: 'api_admin_tasks_')]
#[IsGranted('ROLE_ADMIN')]
final class AdminTaskController extends AbstractController
{
    public function __construct(private readonly ActivityRepository $activities)
    {
    }

    #[Route('', name: 'list', methods: ['GET'])]
    public function list(Request $request): JsonResponse
    {
        $scope = $request->query->get('scope', 'mine');
        $createdBy = null;
        if ('all' !== $scope) {
            $user = $this->getUser();
            // With no resolvable user, "mine" yields nothing rather than all.
            $createdBy = $user instanceof User ? $user : null;
            if (null === $createdBy) {
                return $this->json([]);
            }
        }

        $data = array_map(
            fn (Activity $a): array => $this->serialize($a),
            $this->activities->findTasks($createdBy),
        );

        return $this->json($data);
    }

    /**
     * @return array<string, mixed>
     */
    private function serialize(Activity $a): array
    {
        $customer = $a->getCustomer();
        $opportunity = $a->getOpportunity();
        $contact = $a->getContact();
        $createdBy = $a->getCreatedBy();

        return [
            'id' => $a->getId(),
            'subject' => $a->getSubject(),
            'body' => $a->getBody(),
            'occurredAt' => $a->getOccurredAt()->format(\DateTimeInterface::ATOM),
            'completedAt' => $a->getCompletedAt()?->format(\DateTimeInterface::ATOM),
            'isOpenTask' => $a->isOpenTask(),
            'customerId' => $customer->getId(),
            'customerName' => $customer->getName(),
            'opportunityId' => $opportunity?->getId(),
            'opportunityTitle' => $opportunity?->getTitle(),
            'contactName' => null === $contact ? null : (trim($contact->getLastName().' '.$contact->getFirstName()) ?: $contact->getEmail()),
            'createdByName' => null === $createdBy
                ? null
                : (trim($createdBy->getFirstName().' '.$createdBy->getLastName()) ?: $createdBy->getEmail()),
        ];
    }
}
