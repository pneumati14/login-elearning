<?php

namespace App\Controller;

use App\Entity\Contact;
use App\Entity\Customer;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Security\Http\Attribute\IsGranted;

/**
 * Sub-resource of customer: contact persons at the customer company.
 * Administrators only. Hard delete; contacts are also removed when their
 * customer is deleted (onDelete CASCADE on the entity).
 */
#[Route('/api/admin/customers/{customerId<\d+>}/contacts', name: 'api_admin_customer_contacts_')]
#[IsGranted('ROLE_SALES')]
final class AdminCustomerContactController extends AbstractController
{
    public function __construct(
        private readonly EntityManagerInterface $entityManager,
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

        $contact = new Contact();
        $contact->setCustomer($customer);

        $error = $this->apply($contact, $payload);
        if (null !== $error) {
            return $this->json(['error' => $error], JsonResponse::HTTP_UNPROCESSABLE_ENTITY);
        }

        $this->entityManager->persist($contact);
        $this->entityManager->flush();

        return $this->json($this->serialize($contact), JsonResponse::HTTP_CREATED);
    }

    #[Route('/{id<\d+>}', name: 'update', methods: ['PUT'])]
    public function update(int $customerId, int $id, Request $request): JsonResponse
    {
        $contact = $this->findContact($customerId, $id);
        if (null === $contact) {
            return $this->json(['error' => 'A kapcsolattartó nem található.'], JsonResponse::HTTP_NOT_FOUND);
        }

        $payload = $this->decode($request);
        if (null === $payload) {
            return $this->json(['error' => 'Érvénytelen kérés.'], JsonResponse::HTTP_BAD_REQUEST);
        }

        $error = $this->apply($contact, $payload);
        if (null !== $error) {
            return $this->json(['error' => $error], JsonResponse::HTTP_UNPROCESSABLE_ENTITY);
        }

        $contact->touch();
        $this->entityManager->flush();

        return $this->json($this->serialize($contact));
    }

    #[Route('/{id<\d+>}', name: 'delete', methods: ['DELETE'])]
    public function delete(int $customerId, int $id): JsonResponse
    {
        $contact = $this->findContact($customerId, $id);
        if (null === $contact) {
            return $this->json(['error' => 'A kapcsolattartó nem található.'], JsonResponse::HTTP_NOT_FOUND);
        }

        $this->entityManager->remove($contact);
        $this->entityManager->flush();

        return $this->json(['status' => 'deleted']);
    }

    /**
     * @param array<string, mixed> $payload
     */
    private function apply(Contact $contact, array $payload): ?string
    {
        $firstName = trim((string) ($payload['firstName'] ?? ''));
        $lastName = trim((string) ($payload['lastName'] ?? ''));
        if ('' === $firstName && '' === $lastName) {
            return 'A kapcsolattartó neve kötelező.';
        }

        $contact
            ->setFirstName($firstName)
            ->setLastName($lastName)
            ->setJobTitle($this->nullableString($payload, 'jobTitle'))
            ->setEmail($this->nullableString($payload, 'email'))
            ->setPhone($this->nullableString($payload, 'phone'))
            ->setMobile($this->nullableString($payload, 'mobile'))
            ->setIsPrimary((bool) ($payload['isPrimary'] ?? false))
            ->setNotes($this->nullableString($payload, 'notes'));

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

    private function findContact(int $customerId, int $id): ?Contact
    {
        $contact = $this->entityManager->find(Contact::class, $id);
        if (!$contact instanceof Contact) {
            return null;
        }
        // Make sure the contact really belongs to this customer; we do not
        // want a URL-tampering admin to reach a sibling's row.
        if ($contact->getCustomer()->getId() !== $customerId) {
            return null;
        }
        if (null !== $contact->getCustomer()->getDeletedAt()) {
            return null;
        }

        return $contact;
    }

    /**
     * @param array<string, mixed> $payload
     */
    private function nullableString(array $payload, string $key): ?string
    {
        if (!\array_key_exists($key, $payload)) {
            return null;
        }
        $value = $payload[$key];
        if (!\is_string($value)) {
            return null;
        }
        $trimmed = trim($value);

        return '' === $trimmed ? null : $trimmed;
    }

    /**
     * @return array<string, mixed>
     */
    private function serialize(Contact $c): array
    {
        return [
            'id' => $c->getId(),
            'firstName' => $c->getFirstName(),
            'lastName' => $c->getLastName(),
            'jobTitle' => $c->getJobTitle(),
            'email' => $c->getEmail(),
            'phone' => $c->getPhone(),
            'mobile' => $c->getMobile(),
            'isPrimary' => $c->isPrimary(),
            'notes' => $c->getNotes(),
            'createdAt' => $c->getCreatedAt()->format(\DateTimeInterface::ATOM),
            'updatedAt' => $c->getUpdatedAt()->format(\DateTimeInterface::ATOM),
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
