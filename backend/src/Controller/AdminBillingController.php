<?php

namespace App\Controller;

use App\Entity\BillingItem;
use App\Entity\Customer;
use App\Repository\BillingItemRepository;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Security\Http\Attribute\IsGranted;

/**
 * The billing table: itemised rows to invoice. Rows are snapshotted
 * automatically when a deal is won (see AdminCustomerOpportunityController),
 * can be added by hand, edited, and ticked off as invoiced.
 */
#[Route('/api/admin/billing', name: 'api_admin_billing_')]
#[IsGranted('ROLE_SALES')]
final class AdminBillingController extends AbstractController
{
    public function __construct(
        private readonly EntityManagerInterface $entityManager,
        private readonly BillingItemRepository $billingItems,
    ) {
    }

    #[Route('', name: 'list', methods: ['GET'])]
    public function list(): JsonResponse
    {
        /** @var BillingItem[] $items */
        $items = $this->billingItems->createQueryBuilder('b')
            ->addSelect('c')
            ->join('b.customer', 'c')
            ->andWhere('c.deletedAt IS NULL')
            ->orderBy('b.wonAt', 'DESC')
            ->addOrderBy('b.id', 'DESC')
            ->getQuery()
            ->getResult();

        return $this->json(array_map(fn (BillingItem $b): array => $this->serialize($b), $items));
    }

    /**
     * Add a row by hand. Body: { customerId, name, quantity?, unitPrice?,
     * currency? }. Manual rows carry no opportunity link; their wonAt is
     * the creation day so they sort naturally among the snapshotted ones.
     */
    #[Route('', name: 'create', methods: ['POST'])]
    public function create(Request $request): JsonResponse
    {
        $payload = $this->decode($request);
        if (null === $payload) {
            return $this->json(['error' => 'Érvénytelen kérés.'], JsonResponse::HTTP_BAD_REQUEST);
        }

        $customer = $this->entityManager->find(Customer::class, (int) ($payload['customerId'] ?? 0));
        if (!$customer instanceof Customer || null !== $customer->getDeletedAt()) {
            return $this->json(['error' => 'Az ügyfél nem található.'], JsonResponse::HTTP_UNPROCESSABLE_ENTITY);
        }

        $name = trim((string) ($payload['name'] ?? ''));
        if ('' === $name) {
            return $this->json(['error' => 'A tétel megnevezése kötelező.'], JsonResponse::HTTP_UNPROCESSABLE_ENTITY);
        }

        $item = new BillingItem();
        $item->setCustomer($customer)
            ->setName($name)
            ->setQuantity($this->parseDecimal($payload['quantity'] ?? null) ?? '1')
            ->setUnitPrice($this->parseDecimal($payload['unitPrice'] ?? null) ?? '0')
            ->setCurrency((string) ($payload['currency'] ?? ''))
            ->setWonAt(new \DateTimeImmutable('today'));

        $this->entityManager->persist($item);
        $this->entityManager->flush();

        return $this->json($this->serialize($item), JsonResponse::HTTP_CREATED);
    }

    /** Edit a row. Body: { name, quantity, unitPrice, currency }. */
    #[Route('/{id<\d+>}', name: 'update', methods: ['PUT'])]
    public function update(int $id, Request $request): JsonResponse
    {
        $item = $this->findItem($id);
        if (null === $item) {
            return $this->json(['error' => 'A tétel nem található.'], JsonResponse::HTTP_NOT_FOUND);
        }

        $payload = $this->decode($request);
        if (null === $payload) {
            return $this->json(['error' => 'Érvénytelen kérés.'], JsonResponse::HTTP_BAD_REQUEST);
        }

        $name = trim((string) ($payload['name'] ?? ''));
        if ('' === $name) {
            return $this->json(['error' => 'A tétel megnevezése kötelező.'], JsonResponse::HTTP_UNPROCESSABLE_ENTITY);
        }

        $item->setName($name)
            ->setQuantity($this->parseDecimal($payload['quantity'] ?? null) ?? '1')
            ->setUnitPrice($this->parseDecimal($payload['unitPrice'] ?? null) ?? '0');
        if (\array_key_exists('currency', $payload)) {
            $item->setCurrency((string) $payload['currency']);
        }
        $this->entityManager->flush();

        return $this->json($this->serialize($item));
    }

    /** Flip the invoicing status. Body: { status: "pending"|"invoiced" }. */
    #[Route('/{id<\d+>}/status', name: 'status', methods: ['PUT'])]
    public function status(int $id, Request $request): JsonResponse
    {
        $item = $this->findItem($id);
        if (null === $item) {
            return $this->json(['error' => 'A tétel nem található.'], JsonResponse::HTTP_NOT_FOUND);
        }

        $payload = $this->decode($request);
        $status = (string) ($payload['status'] ?? '');
        if (!\in_array($status, BillingItem::STATUSES, true)) {
            return $this->json(['error' => 'Érvénytelen státusz.'], JsonResponse::HTTP_UNPROCESSABLE_ENTITY);
        }

        $item->setStatus($status);
        $this->entityManager->flush();

        return $this->json($this->serialize($item));
    }

    #[Route('/{id<\d+>}', name: 'delete', methods: ['DELETE'])]
    public function delete(int $id): JsonResponse
    {
        $item = $this->findItem($id);
        if (null === $item) {
            return $this->json(['error' => 'A tétel nem található.'], JsonResponse::HTTP_NOT_FOUND);
        }

        $this->entityManager->remove($item);
        $this->entityManager->flush();

        return $this->json(['status' => 'deleted']);
    }

    private function findItem(int $id): ?BillingItem
    {
        $item = $this->entityManager->find(BillingItem::class, $id);
        if (!$item instanceof BillingItem || null !== $item->getCustomer()->getDeletedAt()) {
            return null;
        }

        return $item;
    }

    /**
     * @return array<string, mixed>
     */
    private function serialize(BillingItem $b): array
    {
        // Per-offer invoicing aggregate from the opportunity's quote lines:
        // the billing menu derives the offer's invoiced %/amount from these.
        $offerTotal = 0.0;
        $offerInvoiced = 0.0;
        $offerLineCount = 0;
        $offerInvoicedCount = 0;
        $opportunity = $b->getOpportunity();
        if (null !== $opportunity) {
            foreach ($opportunity->getLineItems() as $line) {
                ++$offerLineCount;
                $lineTotal = (float) $line->getLineTotal();
                $offerTotal += $lineTotal;
                if ($line->isInvoiced()) {
                    ++$offerInvoicedCount;
                    $offerInvoiced += $lineTotal;
                }
            }
        }

        return [
            'id' => $b->getId(),
            'customerId' => $b->getCustomer()->getId(),
            'customerName' => $b->getCustomer()->getName(),
            'opportunityId' => $b->getOpportunity()?->getId(),
            'opportunityTitle' => $b->getOpportunityTitle(),
            'quoteNumber' => $opportunity?->getQuoteNumber(),
            'cardName' => $b->getCardName(),
            'name' => $b->getName(),
            'quantity' => $b->getQuantity(),
            'unitPrice' => $b->getUnitPrice(),
            'lineTotal' => $b->getLineTotal(),
            'currency' => $b->getCurrency(),
            'status' => $b->getStatus(),
            'wonAt' => $b->getWonAt()?->format('Y-m-d'),
            'invoicedAt' => $b->getInvoicedAt()?->format('Y-m-d'),
            'offerTotalValue' => number_format($offerTotal, 2, '.', ''),
            'offerInvoicedValue' => number_format($offerInvoiced, 2, '.', ''),
            'offerLineCount' => $offerLineCount,
            'offerInvoicedCount' => $offerInvoicedCount,
        ];
    }

    /** Decimal as a clean string, or null for empty/invalid input. */
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
     * @return array<string, mixed>|null
     */
    private function decode(Request $request): ?array
    {
        $payload = json_decode($request->getContent(), true);

        return \is_array($payload) ? $payload : null;
    }
}
