<?php

namespace App\Controller;

use App\Entity\Supplier;
use App\Repository\SupplierRepository;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Security\Http\Attribute\IsGranted;

/**
 * Supplier master list. Reading is open to sales staff (they pick a
 * supplier on customer cards); managing the list is administrators
 * only. Hard delete — card references are set null.
 */
#[Route('/api/admin/suppliers', name: 'api_admin_suppliers_')]
#[IsGranted('ROLE_SALES')]
final class AdminSupplierController extends AbstractController
{
    public function __construct(
        private readonly EntityManagerInterface $entityManager,
        private readonly SupplierRepository $suppliers,
    ) {
    }

    #[Route('', name: 'list', methods: ['GET'])]
    public function list(): JsonResponse
    {
        $data = array_map(
            fn (Supplier $s): array => $this->serialize($s),
            $this->suppliers->findAllOrdered(),
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

        $supplier = new Supplier();
        $error = $this->apply($supplier, $payload);
        if (null !== $error) {
            return $this->json(['error' => $error], JsonResponse::HTTP_UNPROCESSABLE_ENTITY);
        }

        $this->entityManager->persist($supplier);
        $this->entityManager->flush();

        return $this->json($this->serialize($supplier), JsonResponse::HTTP_CREATED);
    }

    #[Route('/{id<\d+>}', name: 'update', methods: ['PUT'])]
    #[IsGranted('ROLE_ADMIN')]
    public function update(Supplier $supplier, Request $request): JsonResponse
    {
        $payload = $this->decode($request);
        if (null === $payload) {
            return $this->json(['error' => 'Érvénytelen kérés.'], JsonResponse::HTTP_BAD_REQUEST);
        }

        $error = $this->apply($supplier, $payload);
        if (null !== $error) {
            return $this->json(['error' => $error], JsonResponse::HTTP_UNPROCESSABLE_ENTITY);
        }

        $supplier->touch();
        $this->entityManager->flush();

        return $this->json($this->serialize($supplier));
    }

    #[Route('/{id<\d+>}', name: 'delete', methods: ['DELETE'])]
    #[IsGranted('ROLE_ADMIN')]
    public function delete(Supplier $supplier): JsonResponse
    {
        $this->entityManager->remove($supplier);
        $this->entityManager->flush();

        return $this->json(['status' => 'deleted']);
    }

    /**
     * @param array<string, mixed> $payload
     */
    private function apply(Supplier $supplier, array $payload): ?string
    {
        $name = trim((string) ($payload['name'] ?? ''));
        if ('' === $name) {
            return 'A beszállító neve kötelező.';
        }

        $supplier
            ->setName($name)
            ->setContactName($this->nullableString($payload, 'contactName'))
            ->setEmail($this->nullableString($payload, 'email'))
            ->setPhone($this->nullableString($payload, 'phone'))
            ->setNotes($this->nullableString($payload, 'notes'));
        if (\array_key_exists('isActive', $payload)) {
            $supplier->setIsActive((bool) $payload['isActive']);
        }

        return null;
    }

    /**
     * @return array<string, mixed>
     */
    private function serialize(Supplier $s): array
    {
        return [
            'id' => $s->getId(),
            'name' => $s->getName(),
            'contactName' => $s->getContactName(),
            'email' => $s->getEmail(),
            'phone' => $s->getPhone(),
            'notes' => $s->getNotes(),
            'isActive' => $s->isActive(),
            'createdAt' => $s->getCreatedAt()->format(\DateTimeInterface::ATOM),
            'updatedAt' => $s->getUpdatedAt()->format(\DateTimeInterface::ATOM),
        ];
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
     * @return array<string, mixed>|null
     */
    private function decode(Request $request): ?array
    {
        $payload = json_decode($request->getContent(), true);

        return \is_array($payload) ? $payload : null;
    }
}
