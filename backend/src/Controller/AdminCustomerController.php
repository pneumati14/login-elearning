<?php

namespace App\Controller;

use App\Entity\Address;
use App\Entity\Contact;
use App\Entity\Customer;
use App\Entity\CustomerCard;
use App\Entity\CustomerFeeItem;
use App\Entity\CustomerInstalledDevice;
use App\Entity\CustomerFeeRaise;
use App\Entity\CustomerSalesAssignment;
use App\Repository\CustomerRepository;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Security\Http\Attribute\IsGranted;

/**
 * Customer (CRM) management — administrators only. Soft-delete: the
 * DELETE route sets deletedAt and listings filter on IS NULL.
 */
#[Route('/api/admin/customers', name: 'api_admin_customers_')]
#[IsGranted('ROLE_SALES')]
final class AdminCustomerController extends AbstractController
{
    public function __construct(
        private readonly EntityManagerInterface $entityManager,
        private readonly CustomerRepository $customers,
    ) {
    }

    #[Route('', name: 'list', methods: ['GET'])]
    public function list(): JsonResponse
    {
        $data = array_map(
            fn (Customer $c): array => $this->serialize($c),
            $this->customers->findAllActive(),
        );

        return $this->json($data);
    }

    #[Route('/{id<\d+>}', name: 'get', methods: ['GET'])]
    public function get(Customer $customer): JsonResponse
    {
        if (null !== $customer->getDeletedAt()) {
            return $this->json(['error' => 'Az ügyfél nem található.'], JsonResponse::HTTP_NOT_FOUND);
        }

        return $this->json($this->serialize($customer));
    }

    #[Route('', name: 'create', methods: ['POST'])]
    public function create(Request $request): JsonResponse
    {
        $payload = $this->decode($request);
        if (null === $payload) {
            return $this->json(['error' => 'Érvénytelen kérés.'], JsonResponse::HTTP_BAD_REQUEST);
        }

        $customer = new Customer();
        $error = $this->apply($customer, $payload);
        if (null !== $error) {
            return $this->json(['error' => $error], JsonResponse::HTTP_UNPROCESSABLE_ENTITY);
        }

        $this->entityManager->persist($customer);
        $this->entityManager->flush();

        return $this->json($this->serialize($customer), JsonResponse::HTTP_CREATED);
    }

    #[Route('/{id<\d+>}', name: 'update', methods: ['PUT'])]
    public function update(Customer $customer, Request $request): JsonResponse
    {
        if (null !== $customer->getDeletedAt()) {
            return $this->json(['error' => 'Az ügyfél nem található.'], JsonResponse::HTTP_NOT_FOUND);
        }

        $payload = $this->decode($request);
        if (null === $payload) {
            return $this->json(['error' => 'Érvénytelen kérés.'], JsonResponse::HTTP_BAD_REQUEST);
        }

        $error = $this->apply($customer, $payload);
        if (null !== $error) {
            return $this->json(['error' => $error], JsonResponse::HTTP_UNPROCESSABLE_ENTITY);
        }

        $customer->touch();
        $this->entityManager->flush();

        return $this->json($this->serialize($customer));
    }

    /**
     * Quick status flip from the overview header — no full payload needed.
     * Body: { "status": "existing" | "potential" }.
     */
    #[Route('/{id<\d+>}/status', name: 'set_status', methods: ['PUT'])]
    public function setStatus(Customer $customer, Request $request): JsonResponse
    {
        if (null !== $customer->getDeletedAt()) {
            return $this->json(['error' => 'Az ügyfél nem található.'], JsonResponse::HTTP_NOT_FOUND);
        }

        $payload = $this->decode($request);
        $status = \is_array($payload) ? (string) ($payload['status'] ?? '') : '';
        if (!\in_array($status, Customer::STATUSES, true)) {
            return $this->json(['error' => 'Érvénytelen státusz.'], JsonResponse::HTTP_UNPROCESSABLE_ENTITY);
        }

        $customer->setStatus($status);
        $customer->touch();
        $this->entityManager->flush();

        return $this->json($this->serialize($customer));
    }

    #[Route('/{id<\d+>}', name: 'delete', methods: ['DELETE'])]
    public function delete(Customer $customer): JsonResponse
    {
        if (null === $customer->getDeletedAt()) {
            $customer->setDeletedAt(new \DateTimeImmutable());
            $customer->touch();
            $this->entityManager->flush();
        }

        return $this->json(['status' => 'deleted']);
    }

    /**
     * @param array<string, mixed> $payload
     */
    private function apply(Customer $customer, array $payload): ?string
    {
        $name = trim((string) ($payload['name'] ?? ''));
        if ('' === $name) {
            return 'Az ügyfél neve kötelező.';
        }

        $customer
            ->setName($name)
            ->setWebsite($this->nullableString($payload, 'website'))
            ->setTaxNumber($this->nullableString($payload, 'taxNumber'))
            ->setEmail($this->nullableString($payload, 'email'))
            ->setPhone($this->nullableString($payload, 'phone'))
            ->setNotes($this->nullableString($payload, 'notes'));

        if (\array_key_exists('status', $payload)) {
            $customer->setStatus((string) $payload['status']);
        }

        $this->applyAddress($customer->getAddress(), $payload['address'] ?? null);
        $this->applyAddress($customer->getBillingAddress(), $payload['billingAddress'] ?? null);

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

        $customer->setValidFrom($from)->setValidUntil($until);

        return null;
    }

    /**
     * Applies an inbound address object onto an embedded Address.
     * A non-array payload (null, missing, scalar) clears the address.
     */
    private function applyAddress(Address $address, mixed $value): void
    {
        if (!\is_array($value)) {
            $address->setCountry(null)->setCity(null)->setPostalCode(null)->setStreet(null);

            return;
        }

        $country = $this->nullableString($value, 'country');
        if (null !== $country) {
            // ISO 3166-1 alpha-2 is uppercase; tolerate lowercase from the client.
            $country = strtoupper($country);
            if (1 !== preg_match('/^[A-Z]{2}$/', $country)) {
                $country = null;
            }
        }
        $address
            ->setCountry($country)
            ->setCity($this->nullableString($value, 'city'))
            ->setPostalCode($this->nullableString($value, 'postalCode'))
            ->setStreet($this->nullableString($value, 'street'));
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
     * @return array<string, mixed>
     */
    private function serialize(Customer $c): array
    {
        $today = new \DateTimeImmutable('today');
        $feeTotals = [];
        foreach ($c->monthlyFeeTotals($today) as $currency => $amount) {
            $feeTotals[] = ['currency' => $currency, 'amount' => $amount];
        }
        // List-price sums, shown next to the discounted total on the billing tab.
        $feeGrossTotals = [];
        foreach ($c->monthlyFeeTotals($today, false) as $currency => $amount) {
            $feeGrossTotals[] = ['currency' => $currency, 'amount' => $amount];
        }

        return [
            'id' => $c->getId(),
            'name' => $c->getName(),
            'status' => $c->getStatus(),
            'monthlyFeeTotals' => $feeTotals,
            'monthlyFeeGrossTotals' => $feeGrossTotals,
            'feeRaises' => array_map(
                static fn (CustomerFeeRaise $r): array => [
                    'id' => $r->getId(),
                    'percent' => $r->getPercent(),
                    'effectiveFrom' => $r->getEffectiveFrom()->format('Y-m-d'),
                    'createdAt' => $r->getCreatedAt()->format(\DateTimeInterface::ATOM),
                ],
                $c->getFeeRaises()->toArray(),
            ),
            'feeItems' => array_map(
                fn (CustomerFeeItem $item): array => $this->serializeFeeItem($item),
                $c->getFeeItems()->toArray(),
            ),
            'cards' => array_map(
                fn (CustomerCard $card): array => AdminCustomerCardController::serializeCard($card),
                $c->getCards()->toArray(),
            ),
            'installedDevices' => array_map(
                fn (CustomerInstalledDevice $device): array => AdminCustomerInstalledDeviceController::serializeDevice($device),
                $c->getInstalledDevices()->toArray(),
            ),
            'address' => $c->getAddress()->toArray(),
            'website' => $c->getWebsite(),
            'billingAddress' => $c->getBillingAddress()->toArray(),
            'taxNumber' => $c->getTaxNumber(),
            'email' => $c->getEmail(),
            'phone' => $c->getPhone(),
            'notes' => $c->getNotes(),
            'validFrom' => $c->getValidFrom()?->format('Y-m-d'),
            'validUntil' => $c->getValidUntil()?->format('Y-m-d'),
            'billing' => AdminCustomerBillingController::serializeBilling($c),
            'salesAssignments' => array_map(
                fn (CustomerSalesAssignment $a): array => $this->serializeAssignment($a),
                $c->getSalesAssignments()->toArray(),
            ),
            'contacts' => array_map(
                fn (Contact $contact): array => $this->serializeContact($contact),
                $c->getContacts()->toArray(),
            ),
            'createdAt' => $c->getCreatedAt()->format(\DateTimeInterface::ATOM),
            'updatedAt' => $c->getUpdatedAt()->format(\DateTimeInterface::ATOM),
        ];
    }

    /**
     * @return array<string, mixed>
     */
    private function serializeAssignment(CustomerSalesAssignment $a): array
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
     * @return array<string, mixed>
     */
    private function serializeFeeItem(CustomerFeeItem $item): array
    {
        return [
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
        ];
    }

    /**
     * @return array<string, mixed>
     */
    private function serializeContact(Contact $c): array
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
