<?php

namespace App\Controller;

use App\Entity\Customer;
use App\Entity\CustomerCard;
use App\Entity\CustomerCardOrder;
use App\Entity\Product;
use App\Entity\Supplier;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Security\Http\Attribute\IsGranted;

/**
 * Sub-resource of customer: cards (type + uniqueness + supplier) and
 * the orders placed for each card (catalogue product, mandatory
 * quantity, order date + status). Every mutation returns the customer's
 * full card list so the client refreshes in one step.
 */
#[Route('/api/admin/customers/{customerId<\d+>}/cards', name: 'api_admin_customer_cards_')]
#[IsGranted('ROLE_SALES')]
final class AdminCustomerCardController extends AbstractController
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

        $card = new CustomerCard();
        $card->setCustomer($customer);
        $error = $this->applyCard($card, $payload);
        if (null !== $error) {
            return $this->json(['error' => $error], JsonResponse::HTTP_UNPROCESSABLE_ENTITY);
        }

        $this->entityManager->persist($card);
        $customer->touch();
        $this->entityManager->flush();
        $this->entityManager->refresh($customer);

        return $this->json($this->serializeCards($customer), JsonResponse::HTTP_CREATED);
    }

    #[Route('/{id<\d+>}', name: 'update', methods: ['PUT'])]
    public function update(int $customerId, int $id, Request $request): JsonResponse
    {
        $card = $this->findCard($customerId, $id);
        if (null === $card) {
            return $this->json(['error' => 'A kártya nem található.'], JsonResponse::HTTP_NOT_FOUND);
        }

        $payload = $this->decode($request);
        if (null === $payload) {
            return $this->json(['error' => 'Érvénytelen kérés.'], JsonResponse::HTTP_BAD_REQUEST);
        }

        $error = $this->applyCard($card, $payload);
        if (null !== $error) {
            return $this->json(['error' => $error], JsonResponse::HTTP_UNPROCESSABLE_ENTITY);
        }

        $card->touch();
        $customer = $card->getCustomer();
        $customer->touch();
        $this->entityManager->flush();
        $this->entityManager->refresh($customer);

        return $this->json($this->serializeCards($customer));
    }

    #[Route('/{id<\d+>}', name: 'delete', methods: ['DELETE'])]
    public function delete(int $customerId, int $id): JsonResponse
    {
        $card = $this->findCard($customerId, $id);
        if (null === $card) {
            return $this->json(['error' => 'A kártya nem található.'], JsonResponse::HTTP_NOT_FOUND);
        }

        $customer = $card->getCustomer();
        $this->entityManager->remove($card);
        $customer->touch();
        $this->entityManager->flush();
        $this->entityManager->refresh($customer);

        return $this->json($this->serializeCards($customer));
    }

    // ── Orders ───────────────────────────────────────────────────────

    #[Route('/{id<\d+>}/orders', name: 'order_create', methods: ['POST'])]
    public function createOrder(int $customerId, int $id, Request $request): JsonResponse
    {
        $card = $this->findCard($customerId, $id);
        if (null === $card) {
            return $this->json(['error' => 'A kártya nem található.'], JsonResponse::HTTP_NOT_FOUND);
        }

        $payload = $this->decode($request);
        if (null === $payload) {
            return $this->json(['error' => 'Érvénytelen kérés.'], JsonResponse::HTTP_BAD_REQUEST);
        }

        $order = new CustomerCardOrder();
        $order->setCard($card);
        $error = $this->applyOrder($order, $payload);
        if (null !== $error) {
            return $this->json(['error' => $error], JsonResponse::HTTP_UNPROCESSABLE_ENTITY);
        }

        $this->entityManager->persist($order);
        $card->touch();
        $customer = $card->getCustomer();
        $customer->touch();
        $this->entityManager->flush();
        $this->entityManager->refresh($customer);

        return $this->json($this->serializeCards($customer), JsonResponse::HTTP_CREATED);
    }

    #[Route('/{id<\d+>}/orders/{orderId<\d+>}', name: 'order_update', methods: ['PUT'])]
    public function updateOrder(int $customerId, int $id, int $orderId, Request $request): JsonResponse
    {
        $order = $this->findOrder($customerId, $id, $orderId);
        if (null === $order) {
            return $this->json(['error' => 'A megrendelés nem található.'], JsonResponse::HTTP_NOT_FOUND);
        }

        $payload = $this->decode($request);
        if (null === $payload) {
            return $this->json(['error' => 'Érvénytelen kérés.'], JsonResponse::HTTP_BAD_REQUEST);
        }

        $error = $this->applyOrder($order, $payload);
        if (null !== $error) {
            return $this->json(['error' => $error], JsonResponse::HTTP_UNPROCESSABLE_ENTITY);
        }

        $order->getCard()->touch();
        $customer = $order->getCard()->getCustomer();
        $customer->touch();
        $this->entityManager->flush();
        $this->entityManager->refresh($customer);

        return $this->json($this->serializeCards($customer));
    }

    /**
     * Move an order to another workflow status (the kanban drag-and-drop).
     * Body: { "status": "quote" | "ordered" | ... }.
     */
    #[Route('/{id<\d+>}/orders/{orderId<\d+>}/status', name: 'order_status', methods: ['PUT'])]
    public function moveOrderStatus(int $customerId, int $id, int $orderId, Request $request): JsonResponse
    {
        $order = $this->findOrder($customerId, $id, $orderId);
        if (null === $order) {
            return $this->json(['error' => 'A megrendelés nem található.'], JsonResponse::HTTP_NOT_FOUND);
        }

        $payload = $this->decode($request);
        $status = \is_array($payload) ? (string) ($payload['status'] ?? '') : '';
        if (!\in_array($status, CustomerCardOrder::STATUSES, true)) {
            return $this->json(['error' => 'Érvénytelen státusz.'], JsonResponse::HTTP_UNPROCESSABLE_ENTITY);
        }

        $order->setStatus($status);
        $order->getCard()->touch();
        $customer = $order->getCard()->getCustomer();
        $customer->touch();
        $this->entityManager->flush();
        $this->entityManager->refresh($customer);

        return $this->json($this->serializeCards($customer));
    }

    #[Route('/{id<\d+>}/orders/{orderId<\d+>}', name: 'order_delete', methods: ['DELETE'])]
    public function deleteOrder(int $customerId, int $id, int $orderId): JsonResponse
    {
        $order = $this->findOrder($customerId, $id, $orderId);
        if (null === $order) {
            return $this->json(['error' => 'A megrendelés nem található.'], JsonResponse::HTTP_NOT_FOUND);
        }

        $customer = $order->getCard()->getCustomer();
        $order->getCard()->touch();
        $this->entityManager->remove($order);
        $customer->touch();
        $this->entityManager->flush();
        $this->entityManager->refresh($customer);

        return $this->json($this->serializeCards($customer));
    }

    /**
     * @param array<string, mixed> $payload
     */
    private function applyCard(CustomerCard $card, array $payload): ?string
    {
        $type = trim((string) ($payload['type'] ?? ''));
        if ('' === $type) {
            return 'A kártya típusa kötelező.';
        }

        if (\array_key_exists('supplierId', $payload)) {
            $supplierId = $payload['supplierId'];
            if (null === $supplierId || '' === $supplierId) {
                $card->setSupplier(null);
            } else {
                $supplier = $this->entityManager->find(Supplier::class, (int) $supplierId);
                if (!$supplier instanceof Supplier) {
                    return 'A megadott beszállító nem található.';
                }
                $card->setSupplier($supplier);
            }
        }

        $card
            ->setType($type)
            ->setUniqueness($this->nullableString($payload, 'uniqueness'));

        return null;
    }

    /**
     * @param array<string, mixed> $payload
     */
    private function applyOrder(CustomerCardOrder $order, array $payload): ?string
    {
        // Orders always come from the catalogue.
        $productId = $payload['productId'] ?? null;
        if (null === $productId || '' === $productId) {
            return 'A termék kiválasztása kötelező.';
        }
        $product = $this->entityManager->find(Product::class, (int) $productId);
        if (!$product instanceof Product) {
            return 'A megadott termék nem található.';
        }
        $order->setProduct($product)->setProductName($product->getName());

        $quantity = $payload['quantity'] ?? null;
        if (!is_numeric($quantity) || (int) $quantity < 1) {
            return 'A darabszám kötelező (legalább 1).';
        }
        $order->setQuantity((int) $quantity);

        // Per-piece prices: optional. The currency is selectable; without an
        // explicit choice it follows the product's catalogue currency.
        $order->setCurrency(
            \array_key_exists('currency', $payload) && \is_string($payload['currency']) && '' !== $payload['currency']
                ? $payload['currency']
                : $product->getCurrency(),
        );
        if (\array_key_exists('unitPurchasePrice', $payload)) {
            $order->setUnitPurchasePrice($this->parseDecimal($payload['unitPurchasePrice']));
        }
        if (\array_key_exists('unitSalePrice', $payload)) {
            $order->setUnitSalePrice($this->parseDecimal($payload['unitSalePrice']));
        }

        if (\array_key_exists('orderedAt', $payload)) {
            $orderedAt = $this->parseDate($payload['orderedAt']);
            if (!$orderedAt instanceof \DateTimeImmutable) {
                return 'A megrendelés dátuma kötelező (YYYY-MM-DD).';
            }
            $order->setOrderedAt($orderedAt);
        }

        if (\array_key_exists('status', $payload)) {
            $order->setStatus((string) $payload['status']);
        }

        return null;
    }

    private function findCustomer(int $customerId): ?Customer
    {
        $customer = $this->entityManager->find(Customer::class, $customerId);
        if (!$customer instanceof Customer || null !== $customer->getDeletedAt()) {
            return null;
        }

        return $customer;
    }

    private function findCard(int $customerId, int $id): ?CustomerCard
    {
        $card = $this->entityManager->find(CustomerCard::class, $id);
        if (!$card instanceof CustomerCard) {
            return null;
        }
        $customer = $card->getCustomer();
        if ($customer->getId() !== $customerId || null !== $customer->getDeletedAt()) {
            return null;
        }

        return $card;
    }

    private function findOrder(int $customerId, int $cardId, int $orderId): ?CustomerCardOrder
    {
        $order = $this->entityManager->find(CustomerCardOrder::class, $orderId);
        if (!$order instanceof CustomerCardOrder) {
            return null;
        }
        if ($order->getCard()->getId() !== $cardId) {
            return null;
        }
        if (null === $this->findCard($customerId, $cardId)) {
            return null;
        }

        return $order;
    }

    /**
     * @return array<string, mixed>
     */
    private function serializeCards(Customer $customer): array
    {
        return [
            'cards' => array_map(
                fn (CustomerCard $card): array => self::serializeCard($card),
                $customer->getCards()->toArray(),
            ),
        ];
    }

    /**
     * Shared with the customer controller so both emit the same shape.
     *
     * @return array<string, mixed>
     */
    public static function serializeCard(CustomerCard $card): array
    {
        $supplier = $card->getSupplier();

        return [
            'id' => $card->getId(),
            'type' => $card->getType(),
            'uniqueness' => $card->getUniqueness(),
            'supplierId' => $supplier?->getId(),
            'supplierName' => $supplier?->getName(),
            'orders' => array_map(
                fn (CustomerCardOrder $o): array => [
                    'id' => $o->getId(),
                    'productId' => $o->getProduct()?->getId(),
                    'productName' => $o->getProductName(),
                    'quantity' => $o->getQuantity(),
                    'unitPurchasePrice' => $o->getUnitPurchasePrice(),
                    'unitSalePrice' => $o->getUnitSalePrice(),
                    'currency' => $o->getCurrency(),
                    'orderedAt' => $o->getOrderedAt()->format('Y-m-d'),
                    'status' => $o->getStatus(),
                    'createdAt' => $o->getCreatedAt()->format(\DateTimeInterface::ATOM),
                ],
                $card->getOrders()->toArray(),
            ),
            'createdAt' => $card->getCreatedAt()->format(\DateTimeInterface::ATOM),
            'updatedAt' => $card->getUpdatedAt()->format(\DateTimeInterface::ATOM),
        ];
    }

    /**
     * Lenient decimal parsing: accepts numbers and numeric strings with a
     * comma decimal separator or spaces; null/empty/garbage become null.
     */
    private function parseDecimal(mixed $value): ?string
    {
        if (null === $value || '' === $value) {
            return null;
        }
        if (\is_int($value) || \is_float($value)) {
            return (string) $value;
        }
        if (\is_string($value)) {
            $normalized = str_replace([' ', ','], ['', '.'], trim($value));
            if ('' === $normalized || !is_numeric($normalized)) {
                return null;
            }

            return $normalized;
        }

        return null;
    }

    /**
     * Parses YYYY-MM-DD; null/empty and malformed values both fail —
     * the order date is mandatory.
     */
    private function parseDate(mixed $value): ?\DateTimeImmutable
    {
        if (!\is_string($value) || '' === trim($value)) {
            return null;
        }
        $parsed = \DateTimeImmutable::createFromFormat('!Y-m-d', trim($value));

        return $parsed instanceof \DateTimeImmutable ? $parsed : null;
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
