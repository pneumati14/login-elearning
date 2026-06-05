<?php

namespace App\Controller;

use App\Entity\Customer;
use App\Entity\CustomerContractFile;
use App\Entity\CustomerPoNumber;
use App\Entity\FeeTitle;
use App\Service\MediaStorage;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\BinaryFileResponse;
use Symfony\Component\HttpFoundation\HeaderUtils;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpFoundation\ResponseHeaderBag;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Security\Http\Attribute\IsGranted;

/**
 * The customer's billing tab: contract number, first invoice date,
 * billing period, fee title and the contract attachments (PDF, Word or
 * image, stored in MediaStorage's "contracts" folder). The monthly fee
 * itself is NOT stored here — it is always the live sum of the fee tab.
 * Every mutation returns the billing block so the client refreshes in
 * one step.
 */
#[Route('/api/admin/customers/{customerId<\d+>}/billing', name: 'api_admin_customer_billing_')]
#[IsGranted('ROLE_SALES')]
final class AdminCustomerBillingController extends AbstractController
{
    private const ALLOWED_MIME_TYPES = [
        'application/pdf',
        'application/msword',
        'application/vnd.openxmlformats-officedocument.wordprocessingml.document',
    ];

    public function __construct(
        private readonly EntityManagerInterface $entityManager,
        private readonly MediaStorage $storage,
    ) {
    }

    #[Route('', name: 'update', methods: ['PUT'])]
    public function update(int $customerId, Request $request): JsonResponse
    {
        $customer = $this->findCustomer($customerId);
        if (null === $customer) {
            return $this->json(['error' => 'Az ügyfél nem található.'], JsonResponse::HTTP_NOT_FOUND);
        }

        $payload = json_decode($request->getContent(), true);
        if (!\is_array($payload)) {
            return $this->json(['error' => 'Érvénytelen kérés.'], JsonResponse::HTTP_BAD_REQUEST);
        }

        if (\array_key_exists('contractNumber', $payload)) {
            $trimmed = trim((string) ($payload['contractNumber'] ?? ''));
            $customer->setContractNumber('' === $trimmed ? null : $trimmed);
        }

        if (\array_key_exists('firstInvoiceDate', $payload)) {
            $raw = $payload['firstInvoiceDate'];
            if (null === $raw || '' === $raw) {
                $customer->setFirstInvoiceDate(null);
            } else {
                $parsed = \DateTimeImmutable::createFromFormat('!Y-m-d', (string) $raw);
                if (!$parsed instanceof \DateTimeImmutable) {
                    return $this->json(['error' => 'Érvénytelen dátum (YYYY-MM-DD).'], JsonResponse::HTTP_UNPROCESSABLE_ENTITY);
                }
                $customer->setFirstInvoiceDate($parsed);
            }
        }

        if (\array_key_exists('billingPeriod', $payload)) {
            $raw = $payload['billingPeriod'];
            if (null !== $raw && '' !== $raw && !\in_array($raw, Customer::BILLING_PERIODS, true)) {
                return $this->json(['error' => 'Érvénytelen számlázási időszak.'], JsonResponse::HTTP_UNPROCESSABLE_ENTITY);
            }
            $customer->setBillingPeriod(\is_string($raw) && '' !== $raw ? $raw : null);
        }

        if (\array_key_exists('billingMode', $payload)) {
            $raw = $payload['billingMode'];
            if (null !== $raw && '' !== $raw && !\in_array($raw, Customer::BILLING_MODES, true)) {
                return $this->json(['error' => 'Érvénytelen számlázási mód.'], JsonResponse::HTTP_UNPROCESSABLE_ENTITY);
            }
            $customer->setBillingMode(\is_string($raw) && '' !== $raw ? $raw : null);
        }

        if (\array_key_exists('paymentDueDays', $payload)) {
            $raw = $payload['paymentDueDays'];
            if (null !== $raw && '' !== $raw && (!is_numeric($raw) || (int) $raw != (float) $raw || (int) $raw < 0 || (int) $raw > 365)) {
                return $this->json(['error' => 'A fizetési határidő 0 és 365 közötti egész szám lehet.'], JsonResponse::HTTP_UNPROCESSABLE_ENTITY);
            }
            $customer->setPaymentDueDays(null === $raw || '' === $raw ? null : (int) $raw);
        }

        if (\array_key_exists('feeDiscountPercent', $payload)) {
            $raw = $payload['feeDiscountPercent'];
            if (null !== $raw && '' !== $raw && (!is_numeric($raw) || (float) $raw < 0 || (float) $raw > 100)) {
                return $this->json(['error' => 'A kedvezmény 0 és 100 közötti szám lehet.'], JsonResponse::HTTP_UNPROCESSABLE_ENTITY);
            }
            $customer->setFeeDiscountPercent(null === $raw || '' === $raw ? null : (string) $raw);
        }

        if (\array_key_exists('hasPo', $payload)) {
            $customer->setHasPo((bool) $payload['hasPo']);
        }

        if (\array_key_exists('feeTitleId', $payload)) {
            $raw = $payload['feeTitleId'];
            if (null === $raw || '' === $raw) {
                $customer->setFeeTitle(null);
            } else {
                $title = $this->entityManager->find(FeeTitle::class, (int) $raw);
                if (!$title instanceof FeeTitle) {
                    return $this->json(['error' => 'A megadott jogcím nem található.'], JsonResponse::HTTP_UNPROCESSABLE_ENTITY);
                }
                $customer->setFeeTitle($title);
            }
        }

        $customer->touch();
        $this->entityManager->flush();

        return $this->json(self::serializeBilling($customer));
    }

    #[Route('/contracts', name: 'contract_upload', methods: ['POST'])]
    public function uploadContract(int $customerId, Request $request): JsonResponse
    {
        $customer = $this->findCustomer($customerId);
        if (null === $customer) {
            return $this->json(['error' => 'Az ügyfél nem található.'], JsonResponse::HTTP_NOT_FOUND);
        }

        $file = $request->files->get('file');
        if (null === $file || !$file->isValid()) {
            return $this->json(['error' => 'Hiányzó vagy hibás fájl.'], JsonResponse::HTTP_BAD_REQUEST);
        }

        $mime = (string) $file->getMimeType();
        if (!\in_array($mime, self::ALLOWED_MIME_TYPES, true) && !str_starts_with($mime, 'image/')) {
            return $this->json(
                ['error' => 'Csak PDF, Word vagy kép tölthető fel.'],
                JsonResponse::HTTP_UNPROCESSABLE_ENTITY,
            );
        }

        $originalName = $file->getClientOriginalName() ?: 'szerzodes';
        $storedName = $this->storage->store($file, 'contracts');

        $contract = (new CustomerContractFile())
            ->setCustomer($customer)
            ->setStoredName($storedName)
            ->setOriginalName(mb_substr($originalName, 0, 255))
            ->setMimeType(mb_substr($mime, 0, 100));
        $this->entityManager->persist($contract);
        $customer->touch();
        $this->entityManager->flush();
        $this->entityManager->refresh($customer);

        return $this->json(self::serializeBilling($customer), JsonResponse::HTTP_CREATED);
    }

    #[Route('/contracts/{fileId<\d+>}', name: 'contract_download', methods: ['GET'])]
    public function downloadContract(int $customerId, int $fileId): Response
    {
        $contract = $this->findContract($customerId, $fileId);
        if (null === $contract) {
            return $this->json(['error' => 'A fájl nem található.'], JsonResponse::HTTP_NOT_FOUND);
        }

        $path = $this->storage->path($contract->getStoredName(), 'contracts');
        if (!is_file($path)) {
            return $this->json(['error' => 'A fájl nem található.'], JsonResponse::HTTP_NOT_FOUND);
        }

        $response = new BinaryFileResponse($path);
        $response->headers->set('Content-Type', $contract->getMimeType());
        // PDFs and images open in the browser; Word documents download.
        $disposition = 'application/pdf' === $contract->getMimeType() || str_starts_with($contract->getMimeType(), 'image/')
            ? ResponseHeaderBag::DISPOSITION_INLINE
            : ResponseHeaderBag::DISPOSITION_ATTACHMENT;
        $response->headers->set('Content-Disposition', HeaderUtils::makeDisposition(
            $disposition,
            $contract->getOriginalName(),
            'szerzodes.'.pathinfo($contract->getStoredName(), \PATHINFO_EXTENSION),
        ));

        return $response;
    }

    #[Route('/contracts/{fileId<\d+>}', name: 'contract_delete', methods: ['DELETE'])]
    public function deleteContract(int $customerId, int $fileId): JsonResponse
    {
        $contract = $this->findContract($customerId, $fileId);
        if (null === $contract) {
            return $this->json(['error' => 'A fájl nem található.'], JsonResponse::HTTP_NOT_FOUND);
        }

        $customer = $contract->getCustomer();
        $this->storage->delete($contract->getStoredName(), 'contracts');
        $this->entityManager->remove($contract);
        $customer->touch();
        $this->entityManager->flush();
        $this->entityManager->refresh($customer);

        return $this->json(self::serializeBilling($customer));
    }

    #[Route('/po-numbers', name: 'po_create', methods: ['POST'])]
    public function createPo(int $customerId, Request $request): JsonResponse
    {
        $customer = $this->findCustomer($customerId);
        if (null === $customer) {
            return $this->json(['error' => 'Az ügyfél nem található.'], JsonResponse::HTTP_NOT_FOUND);
        }

        $po = (new CustomerPoNumber())->setCustomer($customer);
        $error = $this->applyPo($po, $request);
        if (null !== $error) {
            return $this->json(['error' => $error], JsonResponse::HTTP_UNPROCESSABLE_ENTITY);
        }

        $this->entityManager->persist($po);
        $customer->touch();
        $this->entityManager->flush();
        $this->entityManager->refresh($customer);

        return $this->json(self::serializeBilling($customer), JsonResponse::HTTP_CREATED);
    }

    #[Route('/po-numbers/{poId<\d+>}', name: 'po_update', methods: ['PUT'])]
    public function updatePo(int $customerId, int $poId, Request $request): JsonResponse
    {
        $po = $this->findPo($customerId, $poId);
        if (null === $po) {
            return $this->json(['error' => 'A PO szám nem található.'], JsonResponse::HTTP_NOT_FOUND);
        }

        $error = $this->applyPo($po, $request);
        if (null !== $error) {
            return $this->json(['error' => $error], JsonResponse::HTTP_UNPROCESSABLE_ENTITY);
        }

        $customer = $po->getCustomer();
        $customer->touch();
        $this->entityManager->flush();
        $this->entityManager->refresh($customer);

        return $this->json(self::serializeBilling($customer));
    }

    #[Route('/po-numbers/{poId<\d+>}', name: 'po_delete', methods: ['DELETE'])]
    public function deletePo(int $customerId, int $poId): JsonResponse
    {
        $po = $this->findPo($customerId, $poId);
        if (null === $po) {
            return $this->json(['error' => 'A PO szám nem található.'], JsonResponse::HTTP_NOT_FOUND);
        }

        $customer = $po->getCustomer();
        $this->entityManager->remove($po);
        $customer->touch();
        $this->entityManager->flush();
        $this->entityManager->refresh($customer);

        return $this->json(self::serializeBilling($customer));
    }

    /** Shared field handling of the PO create/update payloads. */
    private function applyPo(CustomerPoNumber $po, Request $request): ?string
    {
        $payload = json_decode($request->getContent(), true);
        if (!\is_array($payload)) {
            return 'Érvénytelen kérés.';
        }

        $number = trim((string) ($payload['poNumber'] ?? ''));
        if ('' === $number) {
            return 'A PO szám kötelező.';
        }
        $po->setPoNumber(mb_substr($number, 0, 255));

        foreach (['validFrom', 'validUntil'] as $key) {
            $raw = $payload[$key] ?? null;
            if (null === $raw || '' === $raw) {
                $po->{'set'.ucfirst($key)}(null);
                continue;
            }
            $parsed = \DateTimeImmutable::createFromFormat('!Y-m-d', (string) $raw);
            if (!$parsed instanceof \DateTimeImmutable) {
                return 'Érvénytelen dátum (YYYY-MM-DD).';
            }
            $po->{'set'.ucfirst($key)}($parsed);
        }
        if (null !== $po->getValidFrom() && null !== $po->getValidUntil() && $po->getValidUntil() < $po->getValidFrom()) {
            return 'A záró dátum nem lehet korábbi, mint a kezdő dátum.';
        }

        $notes = $payload['notes'] ?? null;
        $po->setNotes(\is_string($notes) && '' !== trim($notes) ? trim($notes) : null);

        return null;
    }

    private function findPo(int $customerId, int $poId): ?CustomerPoNumber
    {
        $po = $this->entityManager->find(CustomerPoNumber::class, $poId);
        if (!$po instanceof CustomerPoNumber || $po->getCustomer()->getId() !== $customerId) {
            return null;
        }

        return $po;
    }

    /**
     * @return array<string, mixed>
     */
    public static function serializeBilling(Customer $c): array
    {
        return [
            'contractNumber' => $c->getContractNumber(),
            'firstInvoiceDate' => $c->getFirstInvoiceDate()?->format('Y-m-d'),
            'billingPeriod' => $c->getBillingPeriod(),
            'billingMode' => $c->getBillingMode(),
            'paymentDueDays' => $c->getPaymentDueDays(),
            'feeDiscountPercent' => $c->getFeeDiscountPercent(),
            'feeTitleId' => $c->getFeeTitle()?->getId(),
            'feeTitleName' => $c->getFeeTitle()?->getName(),
            'hasPo' => $c->hasPo(),
            'poNumbers' => array_map(
                static fn (CustomerPoNumber $po): array => [
                    'id' => $po->getId(),
                    'poNumber' => $po->getPoNumber(),
                    'validFrom' => $po->getValidFrom()?->format('Y-m-d'),
                    'validUntil' => $po->getValidUntil()?->format('Y-m-d'),
                    'notes' => $po->getNotes(),
                    'createdAt' => $po->getCreatedAt()->format(\DateTimeInterface::ATOM),
                ],
                $c->getPoNumbers()->toArray(),
            ),
            'contractFiles' => array_map(
                static fn (CustomerContractFile $f): array => [
                    'id' => $f->getId(),
                    'name' => $f->getOriginalName(),
                    'mimeType' => $f->getMimeType(),
                    'createdAt' => $f->getCreatedAt()->format(\DateTimeInterface::ATOM),
                ],
                $c->getContractFiles()->toArray(),
            ),
        ];
    }

    private function findCustomer(int $customerId): ?Customer
    {
        $customer = $this->entityManager->find(Customer::class, $customerId);
        if (!$customer instanceof Customer || null !== $customer->getDeletedAt()) {
            return null;
        }

        return $customer;
    }

    private function findContract(int $customerId, int $fileId): ?CustomerContractFile
    {
        $contract = $this->entityManager->find(CustomerContractFile::class, $fileId);
        if (!$contract instanceof CustomerContractFile || $contract->getCustomer()->getId() !== $customerId) {
            return null;
        }

        return $contract;
    }
}
