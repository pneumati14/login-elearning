<?php

namespace App\Controller;

use App\Entity\Activity;
use App\Entity\Customer;
use App\Entity\User;
use App\Repository\ActivityRepository;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Security\Http\Attribute\IsGranted;

/**
 * Cross-customer activity feed + global task creation for the dashboard.
 * The feed returns every activity type (call, meeting, email, note, task),
 * both open and closed — the dashboard filters by status client-side.
 * `scope=mine` (default) limits to activities the current user is
 * responsible for (assignee); `scope=all` returns everyone's. A task
 * created here may stand on its own (no customer) and carry an optional
 * assignee. Editing / closing still goes through the per-customer
 * activity controller (or, for customer-less tasks, this controller).
 */
#[Route('/api/admin/tasks', name: 'api_admin_tasks_')]
#[IsGranted('ROLE_SALES')]
final class AdminTaskController extends AbstractController
{
    public function __construct(
        private readonly ActivityRepository $activities,
        private readonly EntityManagerInterface $entityManager,
    ) {
    }

    #[Route('', name: 'list', methods: ['GET'])]
    public function list(Request $request): JsonResponse
    {
        $scope = $request->query->get('scope', 'mine');
        $assignee = null;
        if ('all' !== $scope) {
            $user = $this->getUser();
            // With no resolvable user, "mine" yields nothing rather than all.
            $assignee = $user instanceof User ? $user : null;
            if (null === $assignee) {
                return $this->json([]);
            }
        }

        $data = array_map(
            fn (Activity $a): array => $this->serialize($a),
            $this->activities->findFeed($assignee),
        );

        return $this->json($data);
    }

    /**
     * Create a task straight from the dashboard. Body:
     * { subject, body?, occurredAt? (due), assigneeId?, customerId? }.
     * Type is always "task"; customer and assignee are optional.
     */
    #[Route('', name: 'create', methods: ['POST'])]
    public function create(Request $request): JsonResponse
    {
        $payload = json_decode($request->getContent(), true);
        if (!\is_array($payload)) {
            return $this->json(['error' => 'Érvénytelen kérés.'], JsonResponse::HTTP_BAD_REQUEST);
        }

        $subject = trim((string) ($payload['subject'] ?? ''));
        if ('' === $subject) {
            return $this->json(['error' => 'A feladat tárgya kötelező.'], JsonResponse::HTTP_UNPROCESSABLE_ENTITY);
        }

        $activity = new Activity();
        $activity->setType(Activity::TYPE_TASK)
            ->setSubject($subject)
            ->setBody($this->nullableString($payload, 'body'))
            ->setOccurredAt($this->parseDateTime($payload['occurredAt'] ?? null) ?? new \DateTimeImmutable());

        $user = $this->getUser();
        if ($user instanceof User) {
            $activity->setCreatedBy($user);
        }

        // Assignee (the responsible user) — optional.
        $assigneeId = $payload['assigneeId'] ?? null;
        if (null !== $assigneeId && '' !== $assigneeId) {
            $assignee = $this->entityManager->find(User::class, (int) $assigneeId);
            if (!$assignee instanceof User) {
                return $this->json(['error' => 'A megadott felelős nem található.'], JsonResponse::HTTP_UNPROCESSABLE_ENTITY);
            }
            $activity->setAssignee($assignee);
        }

        // Customer link — optional.
        $customerId = $payload['customerId'] ?? null;
        if (null !== $customerId && '' !== $customerId) {
            $customer = $this->entityManager->find(Customer::class, (int) $customerId);
            if (!$customer instanceof Customer || null !== $customer->getDeletedAt()) {
                return $this->json(['error' => 'Az ügyfél nem található.'], JsonResponse::HTTP_UNPROCESSABLE_ENTITY);
            }
            $activity->setCustomer($customer);
        }

        $this->entityManager->persist($activity);
        $this->entityManager->flush();

        return $this->json($this->serialize($activity), JsonResponse::HTTP_CREATED);
    }

    /**
     * Close or reopen any activity by id. Body: { "done": bool }. Works
     * dashboard-wide, including customer-less tasks (which have no
     * per-customer URL). Customer-linked items can also be toggled here.
     */
    #[Route('/{id<\d+>}/done', name: 'done', methods: ['PUT'])]
    public function done(int $id, Request $request): JsonResponse
    {
        $activity = $this->entityManager->find(Activity::class, $id);
        if (!$activity instanceof Activity) {
            return $this->json(['error' => 'A feladat nem található.'], JsonResponse::HTTP_NOT_FOUND);
        }

        $payload = json_decode($request->getContent(), true);
        $done = \is_array($payload) ? (bool) ($payload['done'] ?? true) : true;
        $activity->setCompletedAt($done ? new \DateTimeImmutable() : null);
        $activity->touch();
        $this->entityManager->flush();

        return $this->json($this->serialize($activity));
    }

    private function parseDateTime(mixed $value): ?\DateTimeImmutable
    {
        if (!\is_string($value) || '' === trim($value)) {
            return null;
        }
        $value = trim($value);
        $dt = \DateTimeImmutable::createFromFormat('Y-m-d\TH:i', $value)
            ?: \DateTimeImmutable::createFromFormat('Y-m-d\TH:i:s', $value);
        if (false !== $dt) {
            return $dt;
        }
        try {
            return new \DateTimeImmutable($value);
        } catch (\Exception) {
            return null;
        }
    }

    /**
     * @param array<string, mixed> $payload
     */
    private function nullableString(array $payload, string $key): ?string
    {
        if (!\array_key_exists($key, $payload) || !\is_string($payload[$key])) {
            return null;
        }
        $trimmed = trim($payload[$key]);

        return '' === $trimmed ? null : $trimmed;
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
        $assignee = $a->getAssignee();

        return [
            'id' => $a->getId(),
            'type' => $a->getType(),
            'subject' => $a->getSubject(),
            'body' => $a->getBody(),
            'occurredAt' => $a->getOccurredAt()->format(\DateTimeInterface::ATOM),
            'completedAt' => $a->getCompletedAt()?->format(\DateTimeInterface::ATOM),
            'isOpen' => $a->isOpen(),
            'isOpenTask' => $a->isOpenTask(),
            'customerId' => $customer?->getId(),
            'customerName' => $customer?->getName(),
            'opportunityId' => $opportunity?->getId(),
            'opportunityTitle' => $opportunity?->getTitle(),
            'contactName' => null === $contact ? null : (trim($contact->getLastName().' '.$contact->getFirstName()) ?: $contact->getEmail()),
            'createdByName' => null === $createdBy
                ? null
                : (trim($createdBy->getFirstName().' '.$createdBy->getLastName()) ?: $createdBy->getEmail()),
            'assigneeId' => $assignee?->getId(),
            'assigneeName' => null === $assignee
                ? null
                : (trim($assignee->getFirstName().' '.$assignee->getLastName()) ?: $assignee->getEmail()),
        ];
    }
}
