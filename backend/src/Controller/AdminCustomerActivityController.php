<?php

namespace App\Controller;

use App\Entity\Activity;
use App\Entity\Contact;
use App\Entity\Customer;
use App\Entity\Opportunity;
use App\Entity\User;
use App\Repository\ActivityRepository;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Security\Http\Attribute\IsGranted;

/**
 * Sub-resource of customer: the activity log / timeline. Administrators
 * only. Hard delete; activities are removed with their customer
 * (onDelete CASCADE on the entity).
 */
#[Route('/api/admin/customers/{customerId<\d+>}/activities', name: 'api_admin_customer_activities_')]
#[IsGranted('ROLE_SALES')]
final class AdminCustomerActivityController extends AbstractController
{
    public function __construct(
        private readonly EntityManagerInterface $entityManager,
        private readonly ActivityRepository $activities,
    ) {
    }

    #[Route('', name: 'list', methods: ['GET'])]
    public function list(int $customerId, Request $request): JsonResponse
    {
        $customer = $this->findCustomer($customerId);
        if (null === $customer) {
            return $this->json(['error' => 'Az ügyfél nem található.'], JsonResponse::HTTP_NOT_FOUND);
        }

        $opportunityId = $request->query->get('opportunityId');
        $filter = (null === $opportunityId || '' === $opportunityId) ? null : (int) $opportunityId;

        $data = array_map(
            fn (Activity $a): array => $this->serialize($a),
            $this->activities->findForCustomer($customer, $filter),
        );

        return $this->json($data);
    }

    #[Route('', name: 'create', methods: ['POST'])]
    public function create(int $customerId, Request $request): JsonResponse
    {
        $customer = $this->findCustomer($customerId);
        if (null === $customer) {
            return $this->json(['error' => 'Az ügyfél nem található.'], JsonResponse::HTTP_NOT_FOUND);
        }

        $payload = $this->decode($request);
        if (null === $payload) {
            return $this->json(['error' => 'Érvénytelen kérés.'], JsonResponse::HTTP_BAD_REQUEST);
        }

        $activity = new Activity();
        $activity->setCustomer($customer);
        $user = $this->getUser();
        if ($user instanceof User) {
            $activity->setCreatedBy($user);
        }

        $error = $this->apply($activity, $customer, $payload);
        if (null !== $error) {
            return $this->json(['error' => $error], JsonResponse::HTTP_UNPROCESSABLE_ENTITY);
        }

        $this->entityManager->persist($activity);
        $this->entityManager->flush();

        return $this->json($this->serialize($activity), JsonResponse::HTTP_CREATED);
    }

    #[Route('/{id<\d+>}', name: 'update', methods: ['PUT'])]
    public function update(int $customerId, int $id, Request $request): JsonResponse
    {
        $activity = $this->findActivity($customerId, $id);
        if (null === $activity) {
            return $this->json(['error' => 'A tevékenység nem található.'], JsonResponse::HTTP_NOT_FOUND);
        }

        $payload = $this->decode($request);
        if (null === $payload) {
            return $this->json(['error' => 'Érvénytelen kérés.'], JsonResponse::HTTP_BAD_REQUEST);
        }

        $error = $this->apply($activity, $activity->getCustomer(), $payload);
        if (null !== $error) {
            return $this->json(['error' => $error], JsonResponse::HTTP_UNPROCESSABLE_ENTITY);
        }

        $activity->touch();
        $this->entityManager->flush();

        return $this->json($this->serialize($activity));
    }

    /** Mark a task done or re-open it. Body: { "done": bool }. */
    #[Route('/{id<\d+>}/done', name: 'done', methods: ['PUT'])]
    public function done(int $customerId, int $id, Request $request): JsonResponse
    {
        $activity = $this->findActivity($customerId, $id);
        if (null === $activity) {
            return $this->json(['error' => 'A tevékenység nem található.'], JsonResponse::HTTP_NOT_FOUND);
        }

        $payload = $this->decode($request) ?? [];
        $done = (bool) ($payload['done'] ?? true);
        $activity->setCompletedAt($done ? new \DateTimeImmutable() : null);
        $activity->touch();
        $this->entityManager->flush();

        return $this->json($this->serialize($activity));
    }

    #[Route('/{id<\d+>}', name: 'delete', methods: ['DELETE'])]
    public function delete(int $customerId, int $id): JsonResponse
    {
        $activity = $this->findActivity($customerId, $id);
        if (null === $activity) {
            return $this->json(['error' => 'A tevékenység nem található.'], JsonResponse::HTTP_NOT_FOUND);
        }

        $this->entityManager->remove($activity);
        $this->entityManager->flush();

        return $this->json(['status' => 'deleted']);
    }

    /**
     * @param array<string, mixed> $payload
     */
    private function apply(Activity $activity, Customer $customer, array $payload): ?string
    {
        $subject = trim((string) ($payload['subject'] ?? ''));
        if ('' === $subject) {
            return 'A tevékenység tárgya kötelező.';
        }

        $activity->setType((string) ($payload['type'] ?? Activity::TYPE_NOTE))
            ->setSubject($subject)
            ->setBody($this->nullableString($payload, 'body'));

        if (\array_key_exists('occurredAt', $payload)) {
            $occurred = $this->parseDateTime($payload['occurredAt']);
            if (null !== $occurred) {
                $activity->setOccurredAt($occurred);
            }
        }

        // completedAt is generally toggled via the /done endpoint, but accept
        // an explicit value too (e.g. clearing it when changing type).
        if (\array_key_exists('completedAt', $payload)) {
            $activity->setCompletedAt($this->parseDateTime($payload['completedAt']));
        }
        // A non-task can't stay "done".
        if (Activity::TYPE_TASK !== $activity->getType()) {
            $activity->setCompletedAt(null);
        }

        if (\array_key_exists('contactId', $payload)) {
            $contactId = $payload['contactId'];
            if (null === $contactId || '' === $contactId) {
                $activity->setContact(null);
            } else {
                $contact = $this->entityManager->find(Contact::class, (int) $contactId);
                if (!$contact instanceof Contact || $contact->getCustomer()->getId() !== $customer->getId()) {
                    return 'A kapcsolattartó nem ehhez az ügyfélhez tartozik.';
                }
                $activity->setContact($contact);
            }
        }

        if (\array_key_exists('opportunityId', $payload)) {
            $opportunityId = $payload['opportunityId'];
            if (null === $opportunityId || '' === $opportunityId) {
                $activity->setOpportunity(null);
            } else {
                $opportunity = $this->entityManager->find(Opportunity::class, (int) $opportunityId);
                if (!$opportunity instanceof Opportunity || $opportunity->getCustomer()->getId() !== $customer->getId()) {
                    return 'A lehetőség nem ehhez az ügyfélhez tartozik.';
                }
                $activity->setOpportunity($opportunity);
            }
        }

        return null;
    }

    private function findCustomer(int $id): ?Customer
    {
        $customer = $this->entityManager->find(Customer::class, $id);
        if (!$customer instanceof Customer || null !== $customer->getDeletedAt()) {
            return null;
        }

        return $customer;
    }

    private function findActivity(int $customerId, int $id): ?Activity
    {
        $activity = $this->entityManager->find(Activity::class, $id);
        if (!$activity instanceof Activity) {
            return null;
        }
        if ($activity->getCustomer()->getId() !== $customerId) {
            return null;
        }
        if (null !== $activity->getCustomer()->getDeletedAt()) {
            return null;
        }

        return $activity;
    }

    private function parseDateTime(mixed $value): ?\DateTimeImmutable
    {
        if (!\is_string($value) || '' === trim($value)) {
            return null;
        }
        $value = trim($value);
        // Accept the HTML datetime-local format first, then anything parseable.
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
        $contact = $a->getContact();
        $opportunity = $a->getOpportunity();
        $createdBy = $a->getCreatedBy();

        return [
            'id' => $a->getId(),
            'type' => $a->getType(),
            'subject' => $a->getSubject(),
            'body' => $a->getBody(),
            'occurredAt' => $a->getOccurredAt()->format(\DateTimeInterface::ATOM),
            'completedAt' => $a->getCompletedAt()?->format(\DateTimeInterface::ATOM),
            'isOpenTask' => $a->isOpenTask(),
            'contactId' => $contact?->getId(),
            'contactName' => null === $contact ? null : (trim($contact->getLastName().' '.$contact->getFirstName()) ?: $contact->getEmail()),
            'opportunityId' => $opportunity?->getId(),
            'opportunityTitle' => $opportunity?->getTitle(),
            'createdByName' => null === $createdBy
                ? null
                : (trim($createdBy->getFirstName().' '.$createdBy->getLastName()) ?: $createdBy->getEmail()),
            'createdAt' => $a->getCreatedAt()->format(\DateTimeInterface::ATOM),
            'updatedAt' => $a->getUpdatedAt()->format(\DateTimeInterface::ATOM),
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
