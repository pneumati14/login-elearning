<?php

namespace App\Controller;

use App\Entity\Customer;
use App\Entity\CustomerSalesAssignment;
use App\Entity\User;
use App\Repository\UserRepository;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Security\Http\Attribute\IsGranted;

/**
 * Sub-resource of customer: salesperson assignments with from..until
 * periods. Managing assignments (who is responsible for whom) is reserved
 * for sales managers and administrators; plain salespeople see the
 * assignments read-only (served as part of the customer payload).
 * Concurrent / overlapping periods are intentionally allowed (handover
 * and team-share scenarios).
 */
#[Route('/api/admin/customers/{customerId<\d+>}/sales-assignments', name: 'api_admin_customer_sales_')]
#[IsGranted('ROLE_SALES_MANAGER')]
final class AdminCustomerSalesAssignmentController extends AbstractController
{
    public function __construct(
        private readonly EntityManagerInterface $entityManager,
        private readonly UserRepository $users,
    ) {
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

        $assignment = new CustomerSalesAssignment();
        $assignment->setCustomer($customer);

        $error = $this->apply($assignment, $payload);
        if (null !== $error) {
            return $this->json(['error' => $error], JsonResponse::HTTP_UNPROCESSABLE_ENTITY);
        }

        $this->entityManager->persist($assignment);
        $this->entityManager->flush();

        return $this->json($this->serialize($assignment), JsonResponse::HTTP_CREATED);
    }

    #[Route('/{id<\d+>}', name: 'update', methods: ['PUT'])]
    public function update(int $customerId, int $id, Request $request): JsonResponse
    {
        $assignment = $this->findAssignment($customerId, $id);
        if (null === $assignment) {
            return $this->json(['error' => 'A hozzárendelés nem található.'], JsonResponse::HTTP_NOT_FOUND);
        }

        $payload = $this->decode($request);
        if (null === $payload) {
            return $this->json(['error' => 'Érvénytelen kérés.'], JsonResponse::HTTP_BAD_REQUEST);
        }

        $error = $this->apply($assignment, $payload);
        if (null !== $error) {
            return $this->json(['error' => $error], JsonResponse::HTTP_UNPROCESSABLE_ENTITY);
        }

        $this->entityManager->flush();

        return $this->json($this->serialize($assignment));
    }

    #[Route('/{id<\d+>}', name: 'delete', methods: ['DELETE'])]
    public function delete(int $customerId, int $id): JsonResponse
    {
        $assignment = $this->findAssignment($customerId, $id);
        if (null === $assignment) {
            return $this->json(['error' => 'A hozzárendelés nem található.'], JsonResponse::HTTP_NOT_FOUND);
        }

        $this->entityManager->remove($assignment);
        $this->entityManager->flush();

        return $this->json(['status' => 'deleted']);
    }

    /**
     * @param array<string, mixed> $payload
     */
    private function apply(CustomerSalesAssignment $a, array $payload): ?string
    {
        $userId = $payload['userId'] ?? null;
        if (!\is_int($userId) && !ctype_digit((string) $userId)) {
            return 'Válassz értékesítőt.';
        }
        $user = $this->users->find((int) $userId);
        if (!$user instanceof User) {
            return 'A megadott értékesítő nem található.';
        }

        $from = $this->parseDate($payload['validFrom'] ?? null);
        if (false === $from) {
            return 'A kezdő dátum nem értelmezhető (YYYY-MM-DD).';
        }
        $until = $this->parseDate($payload['validUntil'] ?? null);
        if (false === $until) {
            return 'A záró dátum nem értelmezhető (YYYY-MM-DD).';
        }
        if (null !== $from && null !== $until && $until < $from) {
            return 'A záró dátum nem lehet korábbi, mint a kezdő dátum.';
        }

        $notes = $payload['notes'] ?? null;
        $notes = \is_string($notes) ? trim($notes) : null;
        if ('' === $notes) {
            $notes = null;
        }

        $a->setUser($user)->setValidFrom($from)->setValidUntil($until)->setNotes($notes);

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

    private function findAssignment(int $customerId, int $id): ?CustomerSalesAssignment
    {
        $assignment = $this->entityManager->find(CustomerSalesAssignment::class, $id);
        if (!$assignment instanceof CustomerSalesAssignment) {
            return null;
        }
        // Make sure the assignment really belongs to this customer; we
        // do not want a URL-tampering admin to reach a sibling's row.
        if ($assignment->getCustomer()->getId() !== $customerId) {
            return null;
        }
        if (null !== $assignment->getCustomer()->getDeletedAt()) {
            return null;
        }

        return $assignment;
    }

    private function parseDate(mixed $value): \DateTimeImmutable|false|null
    {
        if (null === $value || '' === $value) {
            return null;
        }
        if (!\is_string($value)) {
            return false;
        }
        $parsed = \DateTimeImmutable::createFromFormat('!Y-m-d', $value);

        return $parsed instanceof \DateTimeImmutable ? $parsed : false;
    }

    /**
     * @return array<string, mixed>
     */
    private function serialize(CustomerSalesAssignment $a): array
    {
        $user = $a->getUser();

        return [
            'id' => $a->getId(),
            'userId' => $user->getId(),
            'userName' => trim($user->getFirstName().' '.$user->getLastName()),
            'userEmail' => $user->getEmail(),
            'validFrom' => $a->getValidFrom()?->format('Y-m-d'),
            'validUntil' => $a->getValidUntil()?->format('Y-m-d'),
            'notes' => $a->getNotes(),
            'createdAt' => $a->getCreatedAt()->format(\DateTimeInterface::ATOM),
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
