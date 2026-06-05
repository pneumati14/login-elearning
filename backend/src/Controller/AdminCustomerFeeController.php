<?php

namespace App\Controller;

use App\Entity\Customer;
use App\Entity\CustomerFeeItem;
use App\Entity\Product;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Security\Http\Attribute\IsGranted;

/**
 * Sub-resource of customer: recurring monthly fee items with validity
 * periods. Besides plain CRUD there is a "raise" action for price
 * changes: it closes the item the day before the new price takes effect
 * and opens a new item, keeping the fee history auditable. Every
 * mutation returns the customer's full fee list plus the recomputed
 * active totals so the client refreshes in one step.
 */
#[Route('/api/admin/customers/{customerId<\d+>}/fees', name: 'api_admin_customer_fees_')]
#[IsGranted('ROLE_SALES')]
final class AdminCustomerFeeController extends AbstractController
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

        $item = new CustomerFeeItem();
        $item->setCustomer($customer);
        $error = $this->apply($item, $payload);
        if (null !== $error) {
            return $this->json(['error' => $error], JsonResponse::HTTP_UNPROCESSABLE_ENTITY);
        }

        $this->entityManager->persist($item);
        $customer->touch();
        $this->entityManager->flush();
        $this->entityManager->refresh($customer);

        return $this->json($this->serializeFees($customer), JsonResponse::HTTP_CREATED);
    }

    #[Route('/{id<\d+>}', name: 'update', methods: ['PUT'])]
    public function update(int $customerId, int $id, Request $request): JsonResponse
    {
        $item = $this->findItem($customerId, $id);
        if (null === $item) {
            return $this->json(['error' => 'A havidíj-tétel nem található.'], JsonResponse::HTTP_NOT_FOUND);
        }

        $payload = $this->decode($request);
        if (null === $payload) {
            return $this->json(['error' => 'Érvénytelen kérés.'], JsonResponse::HTTP_BAD_REQUEST);
        }

        $error = $this->apply($item, $payload);
        if (null !== $error) {
            return $this->json(['error' => $error], JsonResponse::HTTP_UNPROCESSABLE_ENTITY);
        }

        $customer = $item->getCustomer();
        $customer->touch();
        $this->entityManager->flush();
        $this->entityManager->refresh($customer);

        return $this->json($this->serializeFees($customer));
    }

    #[Route('/{id<\d+>}', name: 'delete', methods: ['DELETE'])]
    public function delete(int $customerId, int $id): JsonResponse
    {
        $item = $this->findItem($customerId, $id);
        if (null === $item) {
            return $this->json(['error' => 'A havidíj-tétel nem található.'], JsonResponse::HTTP_NOT_FOUND);
        }

        $customer = $item->getCustomer();
        $this->entityManager->remove($item);
        $customer->touch();
        $this->entityManager->flush();
        $this->entityManager->refresh($customer);

        return $this->json($this->serializeFees($customer));
    }

    /**
     * Price (or headcount) change: close this item the day before the
     * change takes effect and open a successor. Body for flat items:
     * { "amount", "effectiveFrom" }; for per-head items:
     * { "unitAmount"?, "quantity"?, "effectiveFrom" } (at least one of
     * unitAmount/quantity).
     */
    /**
     * Raise the WHOLE monthly fee by a percentage: every item active on
     * the effective date is closed the day before and re-opened at the
     * raised price (per-head items raise their unit price). History is
     * kept the same way the per-item raise did.
     * Body: { "percent": 8, "effectiveFrom": "YYYY-MM-DD" }.
     */
    #[Route('/raise-all', name: 'raise_all', methods: ['POST'])]
    public function raiseAll(int $customerId, Request $request): JsonResponse
    {
        $customer = $this->findCustomer($customerId);
        if (null === $customer) {
            return $this->json(['error' => 'Az ügyfél nem található.'], JsonResponse::HTTP_NOT_FOUND);
        }

        $payload = $this->decode($request);
        if (null === $payload) {
            return $this->json(['error' => 'Érvénytelen kérés.'], JsonResponse::HTTP_BAD_REQUEST);
        }

        $percent = $payload['percent'] ?? null;
        if (!is_numeric($percent) || (float) $percent <= -100 || (float) $percent > 1000 || 0.0 === (float) $percent) {
            return $this->json(['error' => 'Az emelés mértéke −100 és 1000 közötti, nullától eltérő szám lehet.'], JsonResponse::HTTP_UNPROCESSABLE_ENTITY);
        }
        $factor = 1 + ((float) $percent) / 100;

        $effectiveFrom = $this->parseDate($payload['effectiveFrom'] ?? null);
        if (!$effectiveFrom instanceof \DateTimeImmutable) {
            return $this->json(['error' => 'A hatályba lépés dátuma kötelező (YYYY-MM-DD).'], JsonResponse::HTTP_UNPROCESSABLE_ENTITY);
        }

        // Items already starting on/after the effective date carry their own
        // future pricing — only items active on the date AND started before
        // it are rolled over.
        $targets = [];
        foreach ($customer->getFeeItems() as $item) {
            if (!$item->isActiveOn($effectiveFrom)) {
                continue;
            }
            if (null !== $item->getValidFrom() && $item->getValidFrom() >= $effectiveFrom) {
                continue;
            }
            $targets[] = $item;
        }
        if ([] === $targets) {
            return $this->json(['error' => 'A megadott napon nincs emelhető aktív tétel.'], JsonResponse::HTTP_UNPROCESSABLE_ENTITY);
        }

        foreach ($targets as $item) {
            $successor = new CustomerFeeItem();
            $successor
                ->setCustomer($customer)
                ->setProduct($item->getProduct())
                ->setName($item->getName())
                ->setCurrency($item->getCurrency())
                ->setNotes($item->getNotes())
                ->setValidFrom($effectiveFrom)
                ->setValidUntil($item->getValidUntil());
            if ($item->isPerHead()) {
                $successor
                    ->setIsPerHead(true)
                    ->setUnitAmount(number_format((float) $item->getUnitAmount() * $factor, 2, '.', ''))
                    ->setQuantity($item->getQuantity());
                $successor->recomputePerHeadAmount();
            } else {
                $successor->setAmount(number_format((float) $item->getAmount() * $factor, 2, '.', ''));
            }
            $item->setValidUntil($effectiveFrom->modify('-1 day'));
            $this->entityManager->persist($successor);
        }

        $customer->touch();
        $this->entityManager->flush();
        $this->entityManager->refresh($customer);

        return $this->json($this->serializeFees($customer), JsonResponse::HTTP_CREATED);
    }

    #[Route('/{id<\d+>}/raise', name: 'raise', methods: ['POST'])]
    public function raise(int $customerId, int $id, Request $request): JsonResponse
    {
        $item = $this->findItem($customerId, $id);
        if (null === $item) {
            return $this->json(['error' => 'A havidíj-tétel nem található.'], JsonResponse::HTTP_NOT_FOUND);
        }

        $payload = $this->decode($request);
        if (null === $payload) {
            return $this->json(['error' => 'Érvénytelen kérés.'], JsonResponse::HTTP_BAD_REQUEST);
        }

        $amount = null;
        $unitAmount = null;
        $quantity = null;
        if ($item->isPerHead()) {
            $unitAmount = $this->parseDecimal($payload['unitAmount'] ?? null);
            $rawQuantity = $payload['quantity'] ?? null;
            $quantity = is_numeric($rawQuantity) && (int) $rawQuantity >= 1 ? (int) $rawQuantity : null;
            if (null === $unitAmount && null === $quantity) {
                return $this->json(['error' => 'Új fajlagos ár vagy új létszám megadása kötelező.'], JsonResponse::HTTP_UNPROCESSABLE_ENTITY);
            }
        } else {
            $amount = $this->parseDecimal($payload['amount'] ?? null);
            if (null === $amount) {
                return $this->json(['error' => 'Az új összeg kötelező.'], JsonResponse::HTTP_UNPROCESSABLE_ENTITY);
            }
        }

        $effectiveFrom = $this->parseDate($payload['effectiveFrom'] ?? null);
        if (!$effectiveFrom instanceof \DateTimeImmutable) {
            return $this->json(['error' => 'A hatályba lépés dátuma kötelező (YYYY-MM-DD).'], JsonResponse::HTTP_UNPROCESSABLE_ENTITY);
        }

        if (null !== $item->getValidUntil() && $item->getValidUntil() < $effectiveFrom) {
            return $this->json(['error' => 'A tétel a megadott dátum előtt már lezárul.'], JsonResponse::HTTP_UNPROCESSABLE_ENTITY);
        }
        if (null !== $item->getValidFrom() && $item->getValidFrom() >= $effectiveFrom) {
            return $this->json(['error' => 'A hatályba lépésnek a tétel kezdete utánra kell esnie.'], JsonResponse::HTTP_UNPROCESSABLE_ENTITY);
        }

        $successor = new CustomerFeeItem();
        $successor
            ->setCustomer($item->getCustomer())
            ->setProduct($item->getProduct())
            ->setName($item->getName())
            ->setCurrency($item->getCurrency())
            ->setNotes($item->getNotes())
            ->setValidFrom($effectiveFrom)
            ->setValidUntil($item->getValidUntil());
        if ($item->isPerHead()) {
            $successor
                ->setIsPerHead(true)
                ->setUnitAmount($unitAmount ?? $item->getUnitAmount())
                ->setQuantity($quantity ?? $item->getQuantity());
            $successor->recomputePerHeadAmount();
        } else {
            $successor->setAmount((string) $amount);
        }
        $item->setValidUntil($effectiveFrom->modify('-1 day'));

        $this->entityManager->persist($successor);
        $customer = $item->getCustomer();
        $customer->touch();
        $this->entityManager->flush();
        $this->entityManager->refresh($customer);

        return $this->json($this->serializeFees($customer), JsonResponse::HTTP_CREATED);
    }

    /**
     * @param array<string, mixed> $payload
     */
    private function apply(CustomerFeeItem $item, array $payload): ?string
    {
        $name = trim((string) ($payload['name'] ?? ''));
        if ('' === $name) {
            return 'A tétel megnevezése kötelező.';
        }

        // Pricing: flat amount, or per-head (unit price × headcount).
        $isPerHead = (bool) ($payload['isPerHead'] ?? false);
        $amount = '0.00';
        if ($isPerHead) {
            $unitAmount = $this->parseDecimal($payload['unitAmount'] ?? null);
            if (null === $unitAmount) {
                return 'A fajlagos ár kötelező létszám-alapú tételnél.';
            }
            $quantity = $payload['quantity'] ?? null;
            if (!is_numeric($quantity) || (int) $quantity < 1) {
                return 'A létszám kötelező (legalább 1).';
            }
            $item->setIsPerHead(true)->setUnitAmount($unitAmount)->setQuantity((int) $quantity);
        } else {
            $amount = $this->parseDecimal($payload['amount'] ?? null);
            if (null === $amount) {
                return 'Az összeg kötelező.';
            }
            $item->setIsPerHead(false)->setUnitAmount(null)->setQuantity(null);
        }

        $from = $this->parseDate($payload['validFrom'] ?? null);
        if (false === $from) {
            return 'Az érvényesség kezdő dátuma nem értelmezhető (YYYY-MM-DD).';
        }
        $until = $this->parseDate($payload['validUntil'] ?? null);
        if (false === $until) {
            return 'Az érvényesség záró dátuma nem értelmezhető (YYYY-MM-DD).';
        }
        if (null !== $from && null !== $until && $until < $from) {
            return 'A záró dátum nem lehet korábbi, mint a kezdő dátum.';
        }

        // Optional catalogue reference — name/amount stay freely editable.
        if (\array_key_exists('productId', $payload)) {
            $productId = $payload['productId'];
            if (null === $productId || '' === $productId) {
                $item->setProduct(null);
            } else {
                $product = $this->entityManager->find(Product::class, (int) $productId);
                if (!$product instanceof Product) {
                    return 'A megadott termék nem található.';
                }
                $item->setProduct($product);
            }
        }

        $item
            ->setName($name)
            ->setAmount($amount)
            ->setCurrency((string) ($payload['currency'] ?? Customer::DEFAULT_CURRENCY))
            ->setNotes($this->nullableString($payload, 'notes'))
            ->setValidFrom($from)
            ->setValidUntil($until);
        $item->recomputePerHeadAmount();

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

    private function findItem(int $customerId, int $id): ?CustomerFeeItem
    {
        $item = $this->entityManager->find(CustomerFeeItem::class, $id);
        if (!$item instanceof CustomerFeeItem) {
            return null;
        }
        $customer = $item->getCustomer();
        if ($customer->getId() !== $customerId || null !== $customer->getDeletedAt()) {
            return null;
        }

        return $item;
    }

    /**
     * The customer's full fee list plus the recomputed active totals.
     *
     * @return array<string, mixed>
     */
    private function serializeFees(Customer $customer): array
    {
        $today = new \DateTimeImmutable('today');
        $totals = [];
        foreach ($customer->monthlyFeeTotals($today) as $currency => $amount) {
            $totals[] = ['currency' => $currency, 'amount' => $amount];
        }
        $grossTotals = [];
        foreach ($customer->monthlyFeeTotals($today, false) as $currency => $amount) {
            $grossTotals[] = ['currency' => $currency, 'amount' => $amount];
        }

        return [
            'feeItems' => array_map(
                fn (CustomerFeeItem $item): array => [
                    'id' => $item->getId(),
                    'productId' => $item->getProduct()?->getId(),
                    'name' => $item->getName(),
                    'isPerHead' => $item->isPerHead(),
                    'unitAmount' => $item->getUnitAmount(),
                    'quantity' => $item->getQuantity(),
                    'amount' => $item->getAmount(),
                    'currency' => $item->getCurrency(),
                    'validFrom' => $item->getValidFrom()?->format('Y-m-d'),
                    'validUntil' => $item->getValidUntil()?->format('Y-m-d'),
                    'notes' => $item->getNotes(),
                    'createdAt' => $item->getCreatedAt()->format(\DateTimeInterface::ATOM),
                ],
                $customer->getFeeItems()->toArray(),
            ),
            'monthlyFeeTotals' => $totals,
            'monthlyFeeGrossTotals' => $grossTotals,
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
     * Parses YYYY-MM-DD; returns null for empty input, the DateTimeImmutable
     * on success, and false on a malformed value so callers can distinguish.
     */
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
