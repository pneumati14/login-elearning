<?php

namespace App\Controller;

use App\Entity\Customer;
use App\Entity\CustomerInstalledDevice;
use App\Entity\Product;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Security\Http\Attribute\IsGranted;

/**
 * Sub-resource of customer: devices installed at the customer site
 * (name, description, quantity, install date, location). The name may be
 * prefilled from the product catalogue but is freely overridable. Hard
 * delete; removed together with the customer (onDelete CASCADE).
 */
#[Route('/api/admin/customers/{customerId<\d+>}/installed-devices', name: 'api_admin_customer_installed_devices_')]
#[IsGranted('ROLE_SALES')]
final class AdminCustomerInstalledDeviceController extends AbstractController
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

        $device = new CustomerInstalledDevice();
        $device->setCustomer($customer);

        $error = $this->apply($device, $payload);
        if (null !== $error) {
            return $this->json(['error' => $error], JsonResponse::HTTP_UNPROCESSABLE_ENTITY);
        }

        $this->entityManager->persist($device);
        $this->entityManager->flush();

        return $this->json(self::serializeDevice($device), JsonResponse::HTTP_CREATED);
    }

    #[Route('/{id<\d+>}', name: 'update', methods: ['PUT'])]
    public function update(int $customerId, int $id, Request $request): JsonResponse
    {
        $device = $this->findDevice($customerId, $id);
        if (null === $device) {
            return $this->json(['error' => 'A telepített eszköz nem található.'], JsonResponse::HTTP_NOT_FOUND);
        }

        $payload = $this->decode($request);
        if (null === $payload) {
            return $this->json(['error' => 'Érvénytelen kérés.'], JsonResponse::HTTP_BAD_REQUEST);
        }

        $error = $this->apply($device, $payload);
        if (null !== $error) {
            return $this->json(['error' => $error], JsonResponse::HTTP_UNPROCESSABLE_ENTITY);
        }

        $device->touch();
        $this->entityManager->flush();

        return $this->json(self::serializeDevice($device));
    }

    #[Route('/{id<\d+>}', name: 'delete', methods: ['DELETE'])]
    public function delete(int $customerId, int $id): JsonResponse
    {
        $device = $this->findDevice($customerId, $id);
        if (null === $device) {
            return $this->json(['error' => 'A telepített eszköz nem található.'], JsonResponse::HTTP_NOT_FOUND);
        }

        $this->entityManager->remove($device);
        $this->entityManager->flush();

        return $this->json(['status' => 'deleted']);
    }

    /**
     * @param array<string, mixed> $payload
     */
    private function apply(CustomerInstalledDevice $device, array $payload): ?string
    {
        // Optional catalogue link; prefills the name on the client but the
        // submitted name wins. Clearing it sends null/''.
        if (\array_key_exists('productId', $payload)) {
            $productId = $payload['productId'];
            if (null === $productId || '' === $productId) {
                $device->setProduct(null);
            } else {
                $product = $this->entityManager->find(Product::class, (int) $productId);
                if (!$product instanceof Product) {
                    return 'A megadott termék nem található.';
                }
                $device->setProduct($product);
            }
        }

        $name = trim((string) ($payload['name'] ?? ''));
        if ('' === $name) {
            return 'Az eszköz neve kötelező.';
        }

        $quantity = $payload['quantity'] ?? null;
        if (!is_numeric($quantity) || (int) $quantity < 1) {
            return 'A darabszám kötelező (legalább 1).';
        }

        if (\array_key_exists('installedAt', $payload)) {
            $installedAt = $this->parseDate($payload['installedAt']);
            if (false === $installedAt) {
                return 'A telepítés dátuma érvénytelen (YYYY-MM-DD).';
            }
            $device->setInstalledAt($installedAt);
        }

        $device
            ->setName($name)
            ->setQuantity((int) $quantity)
            ->setDescription($this->nullableString($payload, 'description'))
            ->setLocation($this->nullableString($payload, 'location'));

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

    private function findDevice(int $customerId, int $id): ?CustomerInstalledDevice
    {
        $device = $this->entityManager->find(CustomerInstalledDevice::class, $id);
        if (!$device instanceof CustomerInstalledDevice) {
            return null;
        }
        $customer = $device->getCustomer();
        if ($customer->getId() !== $customerId || null !== $customer->getDeletedAt()) {
            return null;
        }

        return $device;
    }

    /**
     * Parses YYYY-MM-DD; null/empty → null, malformed → false so callers
     * can reject it.
     */
    private function parseDate(mixed $value): \DateTimeImmutable|false|null
    {
        if (null === $value || '' === $value) {
            return null;
        }
        if (!\is_string($value)) {
            return false;
        }
        $parsed = \DateTimeImmutable::createFromFormat('!Y-m-d', trim($value));

        return $parsed instanceof \DateTimeImmutable ? $parsed : false;
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
     * Shared with the customer controller so both emit the same shape.
     *
     * @return array<string, mixed>
     */
    public static function serializeDevice(CustomerInstalledDevice $d): array
    {
        return [
            'id' => $d->getId(),
            'productId' => $d->getProduct()?->getId(),
            'name' => $d->getName(),
            'description' => $d->getDescription(),
            'quantity' => $d->getQuantity(),
            'installedAt' => $d->getInstalledAt()?->format('Y-m-d'),
            'location' => $d->getLocation(),
            'createdAt' => $d->getCreatedAt()->format(\DateTimeInterface::ATOM),
            'updatedAt' => $d->getUpdatedAt()->format(\DateTimeInterface::ATOM),
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
